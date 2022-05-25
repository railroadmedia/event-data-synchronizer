<?php

namespace Railroad\EventDataSynchronizer\Tests;

use Carbon\Carbon;
use Doctrine\Inflector\InflectorFactory;
use Faker\Generator;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\Ecommerce\Contracts\UserProviderInterface as EcommerceUserProviderInterface;
use Railroad\Ecommerce\Faker\Factory;
use Railroad\Ecommerce\Faker\Faker as EcommerceFaker;
use Railroad\Ecommerce\Providers\EcommerceServiceProvider;
use Railroad\EventDataSynchronizer\Providers\EventDataSynchronizerServiceProvider;
use Railroad\EventDataSynchronizer\Providers\UserProviderInterface;
use Railroad\EventDataSynchronizer\Tests\Fixtures\EcommerceUserProvider;
use Railroad\EventDataSynchronizer\Tests\Fixtures\RailforumsUserProvider;
use Railroad\EventDataSynchronizer\Tests\Fixtures\TestingUserProvider;
use Railroad\Railcontent\Providers\RailcontentServiceProvider;
use Railroad\Railcontent\Repositories\RepositoryBase;
use Railroad\Railforums\Contracts\UserProviderInterface as RailforumsUserProviderInterface;
use Railroad\Railnotifications\Contracts\RailforumProviderInterface;
use Railroad\Usora\Providers\UsoraServiceProvider;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(UserProviderInterface::class, app(TestingUserProvider::class));
        $this->app->instance(RailforumsUserProviderInterface::class, app(RailforumsUserProvider::class));

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
     * @param \Illuminate\Foundation\Application $app
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

        $app['config']->set('ecommerce.database_driver', 'pdo_sqlite');
        $app['config']->set('ecommerce.database_user', 'root');
        $app['config']->set('ecommerce.database_password', 'root');
        $app['config']->set('ecommerce.database_in_memory', true);

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
                    'redis_host' => 'redis',
                    'database_driver' => 'pdo_sqlite',
                    'database_user' => 'root',
                    'database_password' => 'root',
                    'database_in_memory' => true,
                    'enable_query_log' => false,
                    'entities' => [
                        [
                            'path' => __DIR__ . '/../vendor/railroad/ecommerce/src/Entities',
                            'namespace' => 'Railroad\Ecommerce\Entities',
                        ],
                    ],
                ],
            ]
        );

        // usora
        config()->set('usora.authentication_controller_middleware', []);

        // db
        config()->set('usora.data_mode', 'host');
        config()->set('usora.database_connection_name', config('usora.connection_mask_prefix') . 'testbench');
        config()->set('usora.authentication_controller_middleware', []);

        // database
        config()->set('usora.database_user', 'root');
        config()->set('usora.database_password', 'root');
        config()->set('usora.database_driver', 'pdo_sqlite');
        config()->set('usora.database_in_memory', true);

        config()->set('usora.redis_host', 'redis');
        config()->set('usora.development_mode', true);
        config()->set('usora.database_driver', 'pdo_sqlite');
        config()->set('usora.database_user', 'root');
        config()->set('usora.database_password', 'root');
        config()->set('usora.database_in_memory', true);
        config()->set(
            'usora.tables',
            [
                'users' => 'usora_users',
                'user_fields' => 'usora_user_fields',
                'email_changes' => 'usora_email_changes',
                'password_resets' => 'usora_password_resets',
                'remember_tokens' => 'usora_remember_tokens',
                'firebase_tokens' => 'usora_user_firebase_tokens'
            ]
        );

        // if new packages entities are required for testing, their entity directory/namespace config should be merged here
        config()->set(
            'usora.entities',
            [
                [
                    'path' => __DIR__ . '/../vendor/railroad/usora/src/Entities',
                    'namespace' => 'Railroad\Usora\Entities',
                ],
            ]
        );

        config()->set('usora.autoload_all_routes', true);
        config()->set('usora.route_middleware_public_groups', ['test_public_route_group']);
        config()->set('usora.route_middleware_logged_in_groups', ['test_logged_in_route_group']);

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
                    'redis_host' => 'redis',
                ],
            ]
        );

        // register providers
        $app->register(EventDataSynchronizerServiceProvider::class);
        $app->register(EcommerceServiceProvider::class);
        $app->register(RailcontentServiceProvider::class);
        $app->register(UsoraServiceProvider::class);

        app()->instance(EcommerceUserProviderInterface::class, app()->make(EcommerceUserProvider::class));

        // register global doctrine inflector
        $inflector = InflectorFactory::create()->build();

        app()->instance('DoctrineInflector', $inflector);

        // this is required for railcontent connection masking to work properly from test to test
        RepositoryBase::$connectionMask = null;
    }

}