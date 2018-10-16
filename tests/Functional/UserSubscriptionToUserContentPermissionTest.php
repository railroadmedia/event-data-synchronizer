<?php

namespace Railroad\EventDataSynchronizer\Tests;

use Railroad\Ecommerce\Events\SubscriptionEvent;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;

class UserSubscriptionToUserContentPermissionTest extends EventDataSynchronizerTestCase
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

    protected function setUp()
    {
        parent::setUp();

        $this->subscriptionRepository = $this->app->make(SubscriptionRepository::class);
        $this->productRepository = $this->app->make(ProductRepository::class);
        $this->permissionRepository = $this->app->make(PermissionRepository::class);
    }

    public function test_handle_empty_subscription()
    {
        $productSku = 'product_sku';
        $permissionName = 'permission_name';

        $this->app['config']->set(
            'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map',
            [
                $productSku => $permissionName,
            ]
        );

        $product = $this->productRepository->create($this->ecommerceFaker->product(['sku' => $productSku]));
        $permissionId = $this->permissionRepository->create(
            ['name' => $permissionName, 'brand' => config('event-data-synchronizer.brand')]
        );

        // fire the event
        $subscription = $this->subscriptionRepository->create(
            $this->ecommerceFaker->subscription(['product_id' => $product['id']])
        );
    }
}