<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Repositories\UserPermissionsRepository;
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
        $this->handleFromEntity($createdEvent->getUserProduct());
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleUpdated(UserProductUpdated $updatedEvent)
    {
        $this->handleFromEntity($updatedEvent->getNewUserProduct());
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleDeleted(UserProductDeleted $deletedEvent)
    {
        $this->handleFromEntity($deletedEvent->getUserProduct());
    }

    public function handleFromEntity(UserProduct $userProduct)
    {
        $allUsersProducts = $this->userProductRepository->getAllUsersProducts($userProduct->getUser()->getId());

        $permissionsToCreate = [];

        foreach ($allUsersProducts as $allUsersProduct) {
            $permissionName =
                config(
                    'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map.' .
                    $allUsersProduct->getProduct()
                        ->getSku()
                );

            if (empty($permissionName)) {
                continue;
            }

            if (!array_key_exists($permissionName, $permissionsToCreate)) {
                $permissionsToCreate[$permissionName] = $allUsersProduct->getExpirationDate();
            } elseif ($allUsersProduct->getExpirationDate() === null) {
                $permissionsToCreate[$permissionName] = $allUsersProduct->getExpirationDate();
            } elseif (isset($permissionsToCreate[$permissionName]) && $permissionsToCreate[$permissionName] !== null) {
                if (Carbon::parse($permissionsToCreate[$permissionName]) < $allUsersProduct->getExpirationDate()) {
                    $permissionsToCreate[$permissionName] = $allUsersProduct->getExpirationDate();
                }
            }
        }

        foreach ($permissionsToCreate as $permissionNameToSync => $expirationDate) {

            $permissionId =
                $this->permissionRepository->query()
                    ->where('name', $permissionNameToSync)
                    ->where('brand', $allUsersProduct->getProduct()->getBrand())
                    ->first(['id'])['id'] ?? null;

            if (empty($permissionId)) {
                continue;
            }

            $existingPermission =
                $this->userPermissionsRepository->getIdByPermissionAndUser(
                    $allUsersProduct->getUser()->getId(),
                    $permissionId
                )[0] ?? null;

            if (!empty($expirationDate)) {
                $expirationDate = $expirationDate->toDateTimeString();
            }

            if (empty($existingPermission)) {
                $this->userPermissionsRepository->create(
                    [
                        'user_id' => $allUsersProduct->getUser()->getId(),
                        'permission_id' => $permissionId,
                        'start_date' => Carbon::now()
                            ->toDateTimeString(),
                        'expiration_date' => $expirationDate,
                        'created_on' => Carbon::now()
                            ->toDateTimeString(),
                    ]
                );
            } else {
                $this->userPermissionsRepository->update(
                    $existingPermission['id'],
                    [
                        'user_id' => $allUsersProduct->getUser()->getId(),
                        'permission_id' => $permissionId,
                        'expiration_date' => $expirationDate,
                        'updated_on' => Carbon::now()
                            ->toDateTimeString(),
                    ]
                );
            }
        }
    }
}