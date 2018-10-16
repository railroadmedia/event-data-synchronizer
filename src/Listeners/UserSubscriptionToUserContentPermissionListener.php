<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Railroad\Ecommerce\Events\SubscriptionEvent;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Repositories\UserPermissionsRepository;

class UserSubscriptionToUserContentPermissionListener
{
    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;

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
     * @param SubscriptionRepository $subscriptionRepository
     * @param ProductRepository $productRepository
     * @param PermissionRepository $permissionRepository
     * @param UserPermissionsRepository $userPermissionsRepository
     */
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        ProductRepository $productRepository,
        PermissionRepository $permissionRepository,
        UserPermissionsRepository $userPermissionsRepository
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->productRepository = $productRepository;
        $this->permissionRepository = $permissionRepository;
        $this->userPermissionsRepository = $userPermissionsRepository;
    }

    /**
     * @param SubscriptionEvent $subscriptionEvent
     */
    public function handle(SubscriptionEvent $subscriptionEvent)
    {
        $subscription = $this->subscriptionRepository->read($subscriptionEvent->getId());

        if (empty($subscription) || $subscription['brand'] != config('event-data-synchronizer.brand')) {
            return;
        }

        $product = $subscription['product'] ?? $this->productRepository->read($subscription['product_id']);

        if (empty($product)) {
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
            $this->userPermissionsRepository->getIdByPermissionAndUser($subscription['user_id'], $permissionId)[0]
            ??
            null;

        if ($subscription['is_active'] == true &&
            empty($subscription['canceled_on']) &&
            Carbon::parse($subscription['paid_until']) > Carbon::now()) {

            if (empty($existingPermission)) {
                $this->userPermissionsRepository->create(
                    [
                        'user_id' => $subscription['user_id'],
                        'permission_id' => $permissionId,
                        'start_date' => Carbon::now()
                            ->toDateTimeString(),
                        'expiration_date' => Carbon::parse($subscription['paid_until'])
                            ->addDays(3)
                            ->toDateTimeString(),
                        'created_on' => Carbon::now()
                            ->toDateTimeString(),
                    ]
                );
            } else {
                $this->userPermissionsRepository->update(
                    $existingPermission['id'],
                    [
                        'user_id' => $subscription['user_id'],
                        'permission_id' => $permissionId,
                        'expiration_date' => Carbon::parse($subscription['paid_until'])
                            ->addDays(3)
                            ->toDateTimeString(),
                        'updated_on' => Carbon::now()
                            ->toDateTimeString(),
                    ]
                );
            }
        }
    }
}