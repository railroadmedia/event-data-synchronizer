<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Railroad\Ecommerce\Events\Subscriptions\CommandSubscriptionRenewFailed;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Helpers\CacheHelper;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Repositories\UserPermissionsRepository;
use Railroad\Railcontent\Services\ConfigService;
use Railroad\Resora\Events\Created;
use Railroad\Resora\Events\Updated;

class UserProductToUserContentPermissionListener
{
    /**
     * @var UserProductRepository
     */
    private $userProductRepository;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var PermissionRepository
     */
    private $permissionRepository;

    /**
     * @var UserPermissionsRepository
     */
    private $userPermissionsRepository;

    /**
     * UserSubscriptionUserToContentPermissionListener constructor.
     *
     * @param UserProductRepository $userProductRepository
     * @param ProductRepository $productRepository
     * @param PermissionRepository $permissionRepository
     * @param UserPermissionsRepository $userPermissionsRepository
     */
    public function __construct(
        UserProductRepository $userProductRepository,
        ProductRepository $productRepository,
        PermissionRepository $permissionRepository,
        UserPermissionsRepository $userPermissionsRepository
    )
    {
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
        $this->permissionRepository = $permissionRepository;
        $this->userPermissionsRepository = $userPermissionsRepository;
    }

    /**
     * @param Created $createdEvent
     */
    public function handleCreated(UserProductCreated $createdEvent)
    {
        $this->syncUserId($createdEvent->getUserProduct()->getUser()->getId());
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleUpdated(UserProductUpdated $updatedEvent)
    {
        $this->syncUserId($updatedEvent->getNewUserProduct()->getUser()->getId());
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleDeleted(UserProductDeleted $deletedEvent)
    {
        $this->syncUserId($deletedEvent->getUserProduct()->getUser()->getId());
    }

    /**
     * @param  CommandSubscriptionRenewFailed  $commandSubscriptionRenewFailed
     */
    public function handleSubscriptionRenewalFailureFromDatabaseError(
        CommandSubscriptionRenewFailed $commandSubscriptionRenewFailed
    ) {
        try {
            error_log('--- Attempting to recover railcontent renewal permissions ---');
            $this->syncUserId($commandSubscriptionRenewFailed->getSubscription()->getUser()->getId());
            error_log(
                '--- Recovered railcontent renewal permissions successfully! user id: '.
                $commandSubscriptionRenewFailed->getSubscription()->getUser()->getId().' ---'
            );
        } catch (\Exception $exception) {
            error_log(
                '--- Recovered railcontent renewal permissions FAILED! user id: '.
                $commandSubscriptionRenewFailed->getSubscription()->getUser()->getId().' ---'
            );
            error_log($exception);
        }
    }

    public function syncUserId($userId)
    {
        $allUsersProducts = $this->userProductRepository->getAllUsersProducts($userId);

        $permissionsToCreate = [];

        foreach ($allUsersProducts as $allUsersProduct) {

            $permissionNames = $allUsersProduct->getProduct()->getDigitalAccessPermissionNames();

            if (empty($permissionNames)) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                // we need to check by brand as well since some permissions across brands have the same name
                $permissionArrayKey = $permissionName . '|' . $allUsersProduct->getProduct()->getBrand();

                if (!array_key_exists($permissionArrayKey, $permissionsToCreate)) {
                    $permissionsToCreate[$permissionArrayKey] = [
                        'expiration_date' => $allUsersProduct->getExpirationDate(),
                        'start_date' => $allUsersProduct->getStartDate(),
                    ];
                } elseif ($allUsersProduct->getExpirationDate() === null) {
                    $permissionsToCreate[$permissionArrayKey] = [
                        'expiration_date' => $allUsersProduct->getExpirationDate(),
                        'start_date' => $allUsersProduct->getStartDate(),
                    ];
                } elseif (isset($permissionsToCreate[$permissionArrayKey]) &&
                    $permissionsToCreate[$permissionArrayKey] !== null &&
                    !empty($permissionsToCreate[$permissionArrayKey]['expiration_date'])) {
                    if ($permissionsToCreate[$permissionArrayKey]['expiration_date'] < $allUsersProduct->getExpirationDate(
                        )) {
                        $permissionsToCreate[$permissionArrayKey] = [
                            'expiration_date' => $allUsersProduct->getExpirationDate(),
                            'start_date' => $allUsersProduct->getStartDate(),
                        ];;
                    }
                }
            }
        }

        foreach ($permissionsToCreate as $permissionNameAndBrandToSync => $dates) {

            $permissionNameToSync = explode('|', $permissionNameAndBrandToSync)[0];
            $permissionBrandToSync = explode('|', $permissionNameAndBrandToSync)[1];
            $expirationDate = $dates['expiration_date'];
            $startDate = $dates['start_date'] ?? Carbon::now()->toDateTimeString();

            $permissionId =
                $this->permissionRepository->query()
                    ->where('name', $permissionNameToSync)
                    ->where('brand', $permissionBrandToSync)
                    ->first(['id'])['id'] ?? null;

            if (empty($permissionId)) {
                continue;
            }

            $existingPermission =
                $this->userPermissionsRepository->getIdByPermissionAndUser(
                    $userId,
                    $permissionId
                )[0] ?? null;

            if (!empty($expirationDate)) {
                $expirationDate = $expirationDate->toDateTimeString();
            }

            if (empty($existingPermission)) {
                $this->userPermissionsRepository->create(
                    [
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                        'start_date' => $startDate,
                        'expiration_date' => $expirationDate,
                        'created_on' => Carbon::now()
                            ->toDateTimeString(),
                    ]
                );
            } else {
                $this->userPermissionsRepository->update(
                    $existingPermission['id'],
                    [
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                        'expiration_date' => $expirationDate,
                        'start_date' => $startDate,
                        'updated_on' => Carbon::now()
                            ->toDateTimeString(),
                    ]
                );
            }
        }

        // clear the railcontent cache
        CacheHelper::deleteUserFields(
            [
                ConfigService::$redisPrefix . ':userId_' . $userId,
            ],
            'content'
        );
    }
}