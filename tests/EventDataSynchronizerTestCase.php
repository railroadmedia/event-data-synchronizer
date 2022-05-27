<?php

namespace Railroad\EventDataSynchronizer\Tests;

use Carbon\Carbon;
use Doctrine\Inflector\InflectorFactory;
use Faker\Generator;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\Ecommerce\Contracts\UserProviderInterface as EcommerceUserProviderInterface;
use Railroad\Ecommerce\Faker\Factory;
use Railroad\Ecommerce\Faker\Faker as EcommerceFaker;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\Ecommerce\Providers\EcommerceServiceProvider;
use Railroad\EventDataSynchronizer\Providers\EventDataSynchronizerServiceProvider;
use Railroad\EventDataSynchronizer\Providers\UserProviderInterface;
use Railroad\EventDataSynchronizer\Tests\Fixtures\TestingEcommerceUserProvider;
use Railroad\EventDataSynchronizer\Tests\Fixtures\TestingRailforumsUserProvider;
use Railroad\EventDataSynchronizer\Tests\Fixtures\TestingUserProvider;
use Railroad\Railcontent\Providers\RailcontentServiceProvider;
use Railroad\Railcontent\Repositories\RepositoryBase;
use Railroad\Railforums\Contracts\UserProviderInterface as RailforumsUserProviderInterface;
use Railroad\Usora\Managers\UsoraEntityManager;
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

    protected UserProviderInterface $userProvider;

    protected function setUp(): void
    {
        parent::setUp();

        // make sure all entity managers are using the same connection as eachother and laravel
        /**
         * @var EcommerceEntityManager $ecommerceEntityManager
         */
        $ecommerceEntityManager = app(EcommerceEntityManager::class);

        // make sure laravel is using the same connection
        DB::connection('testbench')
            ->setPdo($ecommerceEntityManager->getConnection()->getNativeConnection());

        DB::connection('testbench')
            ->setReadPdo($ecommerceEntityManager->getConnection()->getNativeConnection());

        Schema::connection('testbench')->getConnection()->setPdo(
            $ecommerceEntityManager->getConnection()->getNativeConnection()
        );
        Schema::connection('testbench')->getConnection()->setReadPdo(
            $ecommerceEntityManager->getConnection()->getNativeConnection()
        );

        $this->userProvider = app(TestingUserProvider::class);

        $this->app->instance(UserProviderInterface::class, $this->userProvider);
        $this->app->instance(RailforumsUserProviderInterface::class, app(TestingRailforumsUserProvider::class));

        $this->artisan('migrate:fresh', []);
        $this->artisan('cache:clear', []);

        // This is only here because these migrations now live inside musora-web-platform instead of usora package
        // so we must copy them to this package.
        Schema::connection('testbench')->table('usora_users', function (Blueprint $table) {
            $table->string('access_level')->after('last_used_brand')->nullable()->index();
            $table->integer('total_xp')->after('access_level')->nullable()->index();
            $table->json('brand_method_levels')->after('total_xp')->nullable();
            $table->dateTime('membership_expiration_date')->after('last_used_brand')->nullable()->index();
            $table->boolean('is_lifetime_member')->after('membership_expiration_date')->default(false)->index();
        });

        $this->faker = $this->app->make(Generator::class);
        $this->ecommerceFaker = Factory::create();

        $this->databaseManager = $this->app->make(DatabaseManager::class);
        $this->authManager = $this->app->make(AuthManager::class);
        $this->router = $this->app->make(Router::class);

        Carbon::setTestNow(Carbon::now());
    }

    protected function tearDown(): void
    {
        Schema::connection('testbench')->table('usora_users', function (Blueprint $table) {
            $table->dropColumn(
                ['access_level', 'total_xp', 'brand_method_levels', 'membership_expiration_date', 'is_lifetime_member']
            );
        });

        parent::tearDown();
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
        $app['config']->set('event-data-synchronizer.users_database_connection_name', 'testbench');
        $app['config']->set('event-data-synchronizer.users_table_name', 'usora_users');

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
                    'available_brands' => ['testbench'],
                    'redis_host' => 'redis',
                    'decorators' => [
                        'content' => [
                            \Railroad\Railcontent\Decorators\Hierarchy\ContentSlugHierarchyDecorator::class,
                            \Railroad\Railcontent\Decorators\Entity\ContentEntityDecorator::class,
                        ]
                    ],
                ]
            ]
        );

        // register providers
        $app->register(EventDataSynchronizerServiceProvider::class);
        $app->register(EcommerceServiceProvider::class);

        $ecommerceEntityManager = app(EcommerceEntityManager::class);

        $app->register(RailcontentServiceProvider::class);
        $app->register(UsoraServiceProvider::class);

        // make sure usora is using the same connection as ecom and laravel
        $usoraEntityManager = app(UsoraEntityManager::class);
        $newUsoraEntityManager = UsoraEntityManager::create(
            $ecommerceEntityManager->getConnection(),
            $usoraEntityManager->getConfiguration(),
            $ecommerceEntityManager->getEventManager()
        );
        app()->instance(UsoraEntityManager::class, $newUsoraEntityManager);

        app()->instance(EcommerceUserProviderInterface::class, app()->make(TestingEcommerceUserProvider::class));

        // register global doctrine inflector
        $inflector = InflectorFactory::create()->build();

        app()->instance('DoctrineInflector', $inflector);

        // this is required for railcontent connection masking to work properly from test to test
        RepositoryBase::$connectionMask = null;
    }

    /**
     * @param $email
     * @param $password
     * @return int
     */
    public function createUserAndGetId($email = null, $password = null, $permissionLevel = null)
    {
        return $this->databaseManager->connection('testbench')->table('usora_users')
            ->insertGetId(
                [
                    'email' => $email ?? $this->faker->email,
                    'password' => $password ?? $this->faker->password,
                    'display_name' => $this->faker->name,
                    'permission_level' => $permissionLevel,
                    'created_at' => Carbon::now()
                        ->toDateTimeString(),
                    'updated_at' => Carbon::now()
                        ->toDateTimeString(),
                ]
            );
    }
}