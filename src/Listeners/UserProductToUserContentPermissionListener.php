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
        $userProduct = $deletedEvent->getUserProduct();

        $permissionName =
            config(
                'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map.' .
                $userProduct->getProduct()
                    ->getSku()
            );

        if (empty($permissionName)) {
            return;
        }

        $permissionId =
            $this->permissionRepository->query()
                ->where('name', $permissionName)
                ->where('brand', $userProduct->getProduct()->getBrand())
                ->first(['id'])['id'] ?? null;

        if (empty($permissionId)) {
            return;
        }

        $existingPermission =
            $this->userPermissionsRepository->getIdByPermissionAndUser($userProduct->getUser()->getId(), $permissionId)[0]
            ??
            null;

        $expirationDate = null;

        if (!empty($existingPermission)) {
            $this->userPermissionsRepository->delete($existingPermission['id']);
        }
    }

    private function handleFromEntity(UserProduct $userProduct)
    {
        $permissionName =
            config(
                'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map.' .
                $userProduct->getProduct()
                    ->getSku()
            );

        if (empty($permissionName)) {
            return;
        }

        $permissionId =
            $this->permissionRepository->query()
                ->where('name', $permissionName)
                ->where('brand', $userProduct->getProduct()->getBrand())
                ->first(['id'])['id'] ?? null;

        if (empty($permissionId)) {
            return;
        }

        $existingPermission =
            $this->userPermissionsRepository->getIdByPermissionAndUser($userProduct->getUser()->getId(), $permissionId)[0]
            ??
            null;

        $expirationDate = null;

        if (!empty($userProduct->getExpirationDate())) {
            $expirationDate =
                Carbon::instance($userProduct->getExpirationDate())
                    ->toDateTimeString();
        }

        if (empty($existingPermission)) {
            $this->userPermissionsRepository->create(
                [
                    'user_id' => $userProduct->getUser()->getId(),
                    'permission_id' => $permissionId,
                    'start_date' => Carbon::now()
                        ->toDateTimeString(),
                    'expiration_date' => $expirationDate,
                    'created_on' => Carbon::now()
                        ->toDateTimeString(),
                ]
            );
        }
        else {
            $this->userPermissionsRepository->update(
                $existingPermission['id'],
                [
                    'user_id' => $userProduct->getUser()->getId(),
                    'permission_id' => $permissionId,
                    'expiration_date' => $expirationDate,
                    'updated_on' => Carbon::now()
                        ->toDateTimeString(),
                ]
            );
        }
    }
}