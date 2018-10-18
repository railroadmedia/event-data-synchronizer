<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Repositories\UserPermissionsRepository;
use Railroad\Railcontent\Services\ConfigService;
use Railroad\Resora\Entities\Entity;
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
    public function handleCreated(Created $createdEvent)
    {
        if ($createdEvent->class !== UserProductRepository::class) {
            return;
        }

        $this->handleFromEntity($createdEvent->entity);
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleUpdated(Updated $updatedEvent)
    {
        if ($updatedEvent->class !== UserProductRepository::class) {
            return;
        }

        $this->handleFromEntity($updatedEvent->entity);
    }

    private function handleFromEntity(Entity $userProduct)
    {
        $product = $this->productRepository->read($userProduct['product_id']);

        if (empty($product) || $product['brand'] != config('event-data-synchronizer.brand')) {
            return;
        }

        $permissionName =
            config('event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map.' . $product['sku']);

        if (empty($permissionName)) {
            return;
        }

        $permissionId =
            $this->permissionRepository->query()
                ->where('name', $permissionName)
                ->first(['id'])['id'] ?? null;

        if (empty($permissionId)) {
            return;
        }

        $existingPermission =
            $this->userPermissionsRepository->getIdByPermissionAndUser($userProduct['user_id'], $permissionId)[0]
            ??
            null;

        $expirationDate = null;

        if (!empty($userProduct['expiration_date'])) {
            $expirationDate =
                Carbon::parse($userProduct['expiration_date'])
                    ->addDays(3)
                    ->toDateTimeString();
        }

        if (empty($existingPermission)) {
            $this->userPermissionsRepository->create(
                [
                    'user_id' => $userProduct['user_id'],
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
                    'user_id' => $userProduct['user_id'],
                    'permission_id' => $permissionId,
                    'expiration_date' => $expirationDate,
                    'updated_on' => Carbon::now()
                        ->toDateTimeString(),
                ]
            );
        }

        $c =
            $this->userPermissionsRepository->query()
                ->get();
        $d =
            app(DatabaseManager::class)
                ->connection(ConfigService::$databaseConnectionName)
                ->table(ConfigService::$tableUserPermissions)
                ->get();
    }
}