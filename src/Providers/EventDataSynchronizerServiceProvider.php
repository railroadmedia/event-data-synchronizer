<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Railroad\EventDataSynchronizer\Console\Commands\SetMaropostTagsForExpiredUserProducts;
use Railroad\EventDataSynchronizer\Listeners\Maropost\MaropostEventListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Listeners\DuplicateSubscriptionHandler;
use Railroad\EventDataSynchronizer\Listeners\InfusionsoftSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\Intercom\IntercomSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\Maropost\Providers\MaropostServiceProvider;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;

class EventDataSynchronizerServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
//        UserCreated::class => [
//            IntercomSyncEventListener::class . '@handleUserCreated',
//        ],
//        UserUpdated::class => [
//            IntercomSyncEventListener::class . '@handleUserUpdated',
//        ],
        PaymentMethodCreated::class => [
            IntercomSyncEventListener::class . '@handleUserPaymentMethodCreated',
        ],
        PaymentMethodUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserPaymentMethodUpdated',
        ],
        UserProductCreated::class => [
//            IntercomSyncEventListener::class . '@handleUserProductCreated',
//            InfusionsoftSyncEventListener::class . '@handleUserProductCreated',
//            UserProductToUserContentPermissionListener::class . '@handleCreated',
            MaropostEventListener::class . '@handleUserProductCreated',
        ],
        UserProductUpdated::class => [
//            IntercomSyncEventListener::class . '@handleUserProductUpdated',
//            InfusionsoftSyncEventListener::class . '@handleUserProductUpdated',
//            UserProductToUserContentPermissionListener::class . '@handleUpdated',
            MaropostEventListener::class . '@handleUserProductUpdated',
        ],
        UserProductDeleted::class => [
//            IntercomSyncEventListener::class . '@handleUserProductDeleted',
            InfusionsoftSyncEventListener::class . '@handleUserProductDeleted',
//            UserProductToUserContentPermissionListener::class . '@handleDeleted',
            MaropostEventListener::class . '@handleUserProductDeleted',
        ],
//        SubscriptionCreated::class => [
//            IntercomSyncEventListener::class . '@handleSubscriptionCreated',
//            DuplicateSubscriptionHandler::class . '@handleSubscriptionCreated',
//        ],
//        SubscriptionUpdated::class => [
//            IntercomSyncEventListener::class . '@handleSubscriptionUpdated',
//            DuplicateSubscriptionHandler::class . '@handleSubscriptionUpdated',
//        ],
        OrderEvent::class => [
//            InfusionsoftSyncEventListener::class . '@handleOrderEvent',
        ],
    ];

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->commands([
            SetMaropostTagsForExpiredUserProducts::class
        ]);

        $this->publishes(
            [
                __DIR__ . '/../../config/event-data-synchronizer.php' => config_path('event-data-synchronizer.php'),
                __DIR__ . '/../../config/product_sku_maropost_tag_mapping.php' => config_path(
                    'product_sku_maropost_tag_mapping.php'
                ),
            ]
        );
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(MaropostServiceProvider::class);
    }
}