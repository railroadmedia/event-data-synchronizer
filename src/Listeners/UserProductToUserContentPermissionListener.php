<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
    ) {
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
     * @param CommandSubscriptionRenewFailed $commandSubscriptionRenewFailed
     */
    public function handleSubscriptionRenewalFailureFromDatabaseError(
        CommandSubscriptionRenewFailed $commandSubscriptionRenewFailed
    ) {
        try {
            error_log('--- Attempting to recover railcontent renewal permissions ---');
            $this->syncUserId($commandSubscriptionRenewFailed->getSubscription()->getUser()->getId());
            error_log(
                '--- Recovered railcontent renewal permissions successfully! user id: ' .
                $commandSubscriptionRenewFailed->getSubscription()->getUser()->getId() . ' ---'
            );
        } catch (\Exception $exception) {
            error_log(
                '--- Recovered railcontent renewal permissions FAILED! user id: ' .
                $commandSubscriptionRenewFailed->getSubscription()->getUser()->getId() . ' ---'
            );
            error_log($exception);
        }
    }

    public function syncUserId($userId)
    {
        $allUsersProducts = $this->userProductRepository->getAllUsersProducts($userId);
        $permissions = $this->permissionRepository->getAll();
        $permissionsLookup = [];
        foreach ($permissions as $permission) {
            $key = $permission['brand'] . '_' . $permission['name'];
            $permissionsLookup[$key] = $permission;
        }
        $permissionsToCreate = [];

        foreach ($allUsersProducts as $allUsersProduct) {
            $permissionNames = $allUsersProduct->getProduct()->getDigitalAccessPermissionNames();

            if (empty($permissionNames)) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                // we need to check by brand as well since some permissions across brands have the same name
                $brand = $allUsersProduct->getProduct()->getBrand();
                $keyBrand = $brand . '_' . $permissionName;
                $keyGeneral = 'musora_' . $permissionName;
                $permission = $permissionsLookup[$keyBrand] ?? $permissionsLookup[$keyGeneral] ?? null;
                if (!$permission) {
                    $productName = $allUsersProduct->getProduct()->getId() . ' - ' .
                        $allUsersProduct->getProduct()->getName();
                    Log::error(
                        "Permission $brand - $permissionName does not exist.  Fix issue with product $productName and resync."
                    );
                    continue;
                }
                $permissionId = $permission['id'];

                if (!array_key_exists($permissionId, $permissionsToCreate)) {
                    $permissionsToCreate[$permissionId] = [
                        'expiration_date' => $allUsersProduct->getExpirationDate(),
                        'start_date' => $allUsersProduct->getStartDate(),
                    ];
                } elseif ($allUsersProduct->getExpirationDate() === null) {
                    $permissionsToCreate[$permissionId] = [
                        'expiration_date' => $allUsersProduct->getExpirationDate(),
                        'start_date' => $allUsersProduct->getStartDate(),
                    ];
                } elseif (isset($permissionsToCreate[$permissionId]) &&
                    $permissionsToCreate[$permissionId] !== null &&
                    !empty($permissionsToCreate[$permissionId]['expiration_date'])) {
                    if ($permissionsToCreate[$permissionId]['expiration_date'] < $allUsersProduct->getExpirationDate(
                        )) {
                        $permissionsToCreate[$permissionId] = [
                            'expiration_date' => $allUsersProduct->getExpirationDate(),
                            'start_date' => $allUsersProduct->getStartDate(),
                        ];;
                    }
                }
            }
        }

        foreach ($permissionsToCreate as $permissionId => $dates) {
            $expirationDate = $dates['expiration_date'];
            $startDate = $dates['start_date'] ?? Carbon::now()->toDateTimeString();

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
            } elseif ($existingPermission['expiration_date'] != $expirationDate ||
                $existingPermission['start_date'] > $startDate) {
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