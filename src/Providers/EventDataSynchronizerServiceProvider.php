<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Illuminate\Support\ServiceProvider;
use Railroad\EventDataSynchronizer\Console\Commands\SetMaropostTagsForExpiredUserProducts;
use Railroad\EventDataSynchronizer\Listeners\Intercom\IntercomSyncEventListenerBase;
use Railroad\EventDataSynchronizer\Listeners\Maropost\MaropostEventListener;
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
        UserCreated::class => [
            IntercomSyncEventListenerBase::class . '@handleUserCreated',
        ],
        UserUpdated::class => [
            IntercomSyncEventListenerBase::class . '@handleUserUpdated',
        ],
        PaymentMethodCreated::class => [
            IntercomSyncEventListenerBase::class . '@handleUserPaymentMethodCreated',
        ],
        PaymentMethodUpdated::class => [
            IntercomSyncEventListenerBase::class . '@handleUserPaymentMethodUpdated',
        ],
        UserProductCreated::class => [
            IntercomSyncEventListenerBase::class . '@handleUserProductCreated',

//            InfusionsoftSyncEventListener::class . '@handleUserProductCreated',
//            UserProductToUserContentPermissionListener::class . '@handleCreated',
//            MaropostEventListener::class . '@handleUserProductCreated',
        ],
        UserProductUpdated::class => [
            IntercomSyncEventListenerBase::class . '@handleUserProductUpdated',

//            InfusionsoftSyncEventListener::class . '@handleUserProductUpdated',
//            UserProductToUserContentPermissionListener::class . '@handleUpdated',
//            MaropostEventListener::class . '@handleUserProductUpdated',
        ],
        UserProductDeleted::class => [
            IntercomSyncEventListenerBase::class . '@handleUserProductDeleted',

//            InfusionsoftSyncEventListener::class . '@handleUserProductDeleted',
//            UserProductToUserContentPermissionListener::class . '@handleDeleted',
//            MaropostEventListener::class . '@handleUserProductDeleted',
        ],
        SubscriptionCreated::class => [
            IntercomSyncEventListenerBase::class . '@handleSubscriptionCreated',

//            DuplicateSubscriptionHandler::class . '@handleSubscriptionCreated',
        ],
        SubscriptionUpdated::class => [
            IntercomSyncEventListenerBase::class . '@handleSubscriptionUpdated',

//            DuplicateSubscriptionHandler::class . '@handleSubscriptionUpdated',
        ],
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