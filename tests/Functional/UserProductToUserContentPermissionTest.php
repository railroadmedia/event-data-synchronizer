<?php

namespace Railroad\EventDataSynchronizer\Tests;

use Carbon\Carbon;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Services\ConfigService;

// todo: update tests for new ecom package...
class UserProductToUserContentPermissionTest extends EventDataSynchronizerTestCase
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

    protected function setUp()
    {
        parent::setUp();

        $this->userProductRepository = $this->app->make(UserProductRepository::class);
        $this->productRepository = $this->app->make(ProductRepository::class);
        $this->permissionRepository = $this->app->make(PermissionRepository::class);
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
        $userProduct = $this->userProductRepository->create(
            $this->ecommerceFaker->userProduct(['product_id' => 1])
        );

        $this->assertDatabaseMissing(
            ConfigService::$tableUserPermissions,
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
        $userProduct = $this->userProductRepository->create(
            $this->ecommerceFaker->userProduct(['product_id' => 1])
        );

        $this->assertDatabaseMissing(
            ConfigService::$tableUserPermissions,
            [
                'permission_id' => 1,
            ]
        );
    }

    public function test_handle_user_product_created_no_existing_permission_no_expiration()
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
        $userProduct = $this->userProductRepository->create(
            $this->ecommerceFaker->userProduct(['product_id' => $product['id']])
        );

        $a =
            $this->databaseManager->connection(ConfigService::$databaseConnectionName)
                ->table(ConfigService::$tableUserPermissions)
                ->get();

        $this->assertDatabaseHas(
            ConfigService::$tableUserPermissions,
            [
                'user_id' => $userProduct['user_id'],
                'permission_id' => $permissionId,
                'start_date' => Carbon::now()
                    ->toDateTimeString(),
                'expiration_date' => null,
                'created_on' => Carbon::now()
                    ->toDateTimeString(),
            ]
        );
    }

    public function test_handle_user_product_created_no_existing_permission_with_expiration()
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
        $userProduct = $this->userProductRepository->create(
            $this->ecommerceFaker->userProduct(
                [
                    'product_id' => $product['id'],
                    'expiration_date' => Carbon::now()
                        ->addMonth()
                        ->toDateTimeString(),
                ]
            )
        );

        $this->assertDatabaseHas(
            ConfigService::$tableUserPermissions,
            [
                'user_id' => $userProduct['user_id'],
                'permission_id' => $permissionId,
                'start_date' => Carbon::now()
                    ->toDateTimeString(),
                'expiration_date' => Carbon::now()
                    ->addMonth()
                    ->addDays(3)
                    ->toDateTimeString(),
                'created_on' => Carbon::now()
                    ->toDateTimeString(),
            ]
        );
    }

    public function test_handle_user_product_updated_with_existing_permission()
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

        $userProduct = $this->userProductRepository->create(
            $this->ecommerceFaker->userProduct(['product_id' => $product['id']])
        );

        // fire the event
        $userProduct = $this->userProductRepository->update(
            $userProduct['id'],
            [
                'expiration_date' => Carbon::now()
                    ->addDays(10)
                    ->toDateTimeString(),
            ]
        );

        $this->assertDatabaseHas(
            ConfigService::$tableUserPermissions,
            [
                'user_id' => $userProduct['user_id'],
                'permission_id' => $permissionId,
                'start_date' => Carbon::now()
                    ->toDateTimeString(),
                'expiration_date' => Carbon::parse($userProduct['expiration_date'])
                    ->addDays(3)
                    ->toDateTimeString(),
                'created_on' => Carbon::now()
                    ->toDateTimeString(),
                'updated_on' => Carbon::now()
                    ->toDateTimeString(),
            ]
        );
    }

    public function test_handle_user_product_deleted()
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

        $userProduct = $this->userProductRepository->create(
            $this->ecommerceFaker->userProduct(['product_id' => $product['id']])
        );

        // fire the event
        $userProduct = $this->userProductRepository->destroy(
            $userProduct['id']
        );

        $this->assertDatabaseMissing(
            ConfigService::$tableUserPermissions,
            [
                'user_id' => $userProduct['user_id'],
                'permission_id' => $permissionId,
            ]
        );
    }
}