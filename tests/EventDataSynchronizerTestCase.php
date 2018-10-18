<?php

namespace Railroad\EventDataSynchronizer\Tests;

use Carbon\Carbon;
use Faker\Generator;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\Ecommerce\Faker\Factory;
use Railroad\Ecommerce\Faker\Faker as EcommerceFaker;
use Railroad\Ecommerce\Providers\EcommerceServiceProvider;
use Railroad\EventDataSynchronizer\Providers\EventDataSynchronizerServiceProvider;
use Railroad\Railcontent\Providers\RailcontentServiceProvider;
use Railroad\Railcontent\Repositories\RepositoryBase;

class EventDataSynchronizerTestCase extends BaseTestCase
{
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var EcommerceFaker
     */
    protected $ecommerceFaker;

    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var AuthManager
     */
    protected $authManager;

    /**
     * @var Router
     */
    protected $router;

    protected function setUp()
    {
        parent::setUp();

        $this->artisan('migrate:fresh', []);
        $this->artisan('cache:clear', []);

        $this->faker = $this->app->make(Generator::class);
        $this->ecommerceFaker = Factory::create();

        $this->databaseManager = $this->app->make(DatabaseManager::class);
        $this->authManager = $this->app->make(AuthManager::class);
        $this->router = $this->app->make(Router::class);

        Carbon::setTestNow(Carbon::now());
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set(
            'database.connections.testbench',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]
        );

        $app['config']->set('event-data-synchronizer.brand', 'testbench');
        $app['config']->set(
            'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map',
            [
                'product_sku' => 'permission_name',
            ]
        );

        // ecommerce
        config(
            [
                'ecommerce' => [
                    'database_connection_name' => 'testbench',
                    'cache_duration' => 60,
                    'table_prefix' => 'ecommerce_',
                    'data_mode' => 'host',
                    'brand' => 'testbench',
                    'typeSubscription' => 'subscription',
                    'typeProduct' => 'product',
                ],
            ]
        );

        // railcontent
        config(
            [
                'railcontent' => [
                    'database_connection_name' => 'testbench',
                    'connection_mask_prefix' => 'railcontent_',
                    'data_mode' => 'host',
                    'table_prefix' => 'railcontent_',
                    'brand' => 'testbench',
                    'available_brand' => 'testbench',
                ],
            ]
        );

        // register providers
        $app->register(EventDataSynchronizerServiceProvider::class);
        $app->register(EcommerceServiceProvider::class);
        $app->register(RailcontentServiceProvider::class);

        // this is required for railcontent connection masking to work properly from test to test
        RepositoryBase::$connectionMask = null;
    }

}