<?php

namespace Railroad\EventDataSynchronizer\Providers;

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
use Railroad\EventDataSynchronizer\Listeners\IntercomSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\Resora\Events\Created;
use Railroad\Resora\Events\Updated;
use Railroad\Usora\Events\UserCreated;
use Railroad\Usora\Events\UserUpdated;

class EventDataSynchronizerServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Created::class => [
            UserProductToUserContentPermissionListener::class . '@handleCreated',
        ],
        Updated::class => [
            UserProductToUserContentPermissionListener::class . '@handleUpdated',
        ],
        UserCreated::class => [
            IntercomSyncEventListener::class . '@handleUserCreated',
        ],
        UserUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserUpdated',
        ],
        PaymentMethodCreated::class => [
            IntercomSyncEventListener::class . '@handleUserPaymentMethodCreated',
        ],
        PaymentMethodUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserPaymentMethodUpdated',
        ],
        UserProductCreated::class => [
            IntercomSyncEventListener::class . '@handleUserProductCreated',
            InfusionsoftSyncEventListener::class . '@handleUserProductCreated',
        ],
        UserProductUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserProductUpdated',
            InfusionsoftSyncEventListener::class . '@handleUserProductUpdated',
        ],
        UserProductDeleted::class => [
            IntercomSyncEventListener::class . '@handleUserProductDeleted',
            InfusionsoftSyncEventListener::class . '@handleUserProductDeleted',
        ],
        SubscriptionCreated::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionCreated',
            DuplicateSubscriptionHandler::class . '@handleSubscriptionCreated',
        ],
        SubscriptionUpdated::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionUpdated',
            DuplicateSubscriptionHandler::class . '@handleSubscriptionUpdated',
        ],
        OrderEvent::class => [
            InfusionsoftSyncEventListener::class . '@handleOrderEvent',
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

        $this->publishes(
            [
                __DIR__ . '/../../config/event-data-synchronizer.php' => config_path('event-data-synchronizer.php'),
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

    }
}