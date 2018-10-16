<?php

namespace Railroad\EventDataSynchronizer\Tests;

use Carbon\Carbon;
use Railroad\Ecommerce\Events\SubscriptionEvent;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Services\ConfigService;

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

        event(new SubscriptionEvent(1, 'created'));

        $this->assertDatabaseMissing(
            config('railcontent.tables.railcontent_user_permissions'),
            [
                'permission_id' => $permissionId,
            ]
        );
    }

    public function test_handle_empty_product()
    {
        $productSku = 'product_sku';
        $permissionName = 'permission_name';

        $this->app['config']->set(
            'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map',
            [
                $productSku => $permissionName,
            ]
        );

        $permissionId = $this->permissionRepository->create(
            ['name' => $permissionName, 'brand' => config('event-data-synchronizer.brand')]
        );

        // fire the event
        $subscription = $this->subscriptionRepository->create(
            $this->ecommerceFaker->subscription(['product_id' => 1])
        );

        $this->assertDatabaseMissing(
            config('railcontent.tables.railcontent_user_permissions'),
            [
                'permission_id' => $permissionId,
            ]
        );
    }

    public function test_handle_empty_permission()
    {
        $productSku = 'product_sku';
        $permissionName = 'permission_name';

        $this->app['config']->set(
            'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map',
            [
                $productSku => $permissionName,
            ]
        );

        // fire the event
        $subscription = $this->subscriptionRepository->create(
            $this->ecommerceFaker->subscription(['product_id' => 1])
        );

        $this->assertDatabaseMissing(
            config('railcontent.tables.railcontent_user_permissions'),
            [
                'permission_id' => 1,
            ]
        );
    }

    public function test_handle_subscription_created_no_existing_permission()
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

        $this->assertDatabaseHas(
            ConfigService::$tableUserPermissions,
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
    }

    public function test_handle_subscription_updated_with_existing_permission()
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

        $subscription = $this->subscriptionRepository->create(
            $this->ecommerceFaker->subscription(['product_id' => $product['id']])
        );

        // fire the event
        $subscription = $this->subscriptionRepository->update(
            $subscription['id'],
            [
                'paid_until' => Carbon::now()
                    ->addDays(10)
                    ->toDateTimeString(),
            ]
        );

        $this->assertDatabaseHas(
            ConfigService::$tableUserPermissions,
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
                'updated_on' => Carbon::now()
                    ->toDateTimeString(),
            ]
        );
    }

    public function test_handle_subscription_updated_canceled()
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

        $subscription = $this->subscriptionRepository->create(
            $this->ecommerceFaker->subscription(['product_id' => $product['id']])
        );

        // fire the event
        $subscription = $this->subscriptionRepository->update(
            $subscription['id'],
            [
                'is_active' => false,
                'canceled_on' => Carbon::now()
                    ->toDateTimeString(),
            ]
        );

        $this->assertDatabaseHas(
            ConfigService::$tableUserPermissions,
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
    }

    public function test_handle_subscription_created_canceled()
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
            $this->ecommerceFaker->subscription(
                [
                    'product_id' => $product['id'],
                    'is_active' => false,
                    'canceled_on' => Carbon::now()
                        ->toDateTimeString(),
                ]
            )
        );

        $this->assertDatabaseMissing(
            ConfigService::$tableUserPermissions,
            [
                'user_id' => $subscription['user_id'],
                'permission_id' => $permissionId,
            ]
        );
    }
}