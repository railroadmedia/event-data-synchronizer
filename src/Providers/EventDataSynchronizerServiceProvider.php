<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Railroad\Ecommerce\Events\AppSignupFinishedEvent;
use Railroad\Ecommerce\Events\AppSignupStartedEvent;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Console\Commands\IntercomReSyncTool;
use Railroad\EventDataSynchronizer\Console\Commands\SetMaropostTagsForExpiredUserProducts;
use Railroad\EventDataSynchronizer\Console\Commands\UserContentPermissionsResyncTool;
use Railroad\EventDataSynchronizer\Listeners\Intercom\IntercomSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\Maropost\MaropostEventListener;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\EventDataSynchronizer\Services\IntercomSyncService;
use Railroad\EventDataSynchronizer\Services\IntercomSyncServiceBase;
use Railroad\Maropost\Providers\MaropostServiceProvider;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;

class EventDataSynchronizerServiceProvider extends EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
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
            UserProductToUserContentPermissionListener::class . '@handleCreated',
            MaropostEventListener::class . '@handleUserProductCreated',
        ],
        UserProductUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserProductUpdated',
            UserProductToUserContentPermissionListener::class . '@handleUpdated',
            MaropostEventListener::class . '@handleUserProductUpdated',
        ],
        UserProductDeleted::class => [
            IntercomSyncEventListener::class . '@handleUserProductDeleted',
            UserProductToUserContentPermissionListener::class . '@handleDeleted',
            MaropostEventListener::class . '@handleUserProductDeleted',
        ],
        SubscriptionCreated::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionCreated',
            MaropostEventListener::class . '@handleSubscriptionCreated',
        ],
        SubscriptionUpdated::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionUpdated',
            MaropostEventListener::class . '@handleSubscriptionUpdated',
        ],
        SubscriptionRenewFailed::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionRenewalAttemptFailed',
        ],
        OrderEvent::class => [],
        AppSignupStartedEvent::class => [
            IntercomSyncEventListener::class . '@handleAppSignupStarted',
        ],
        AppSignupFinishedEvent::class => [
            IntercomSyncEventListener::class . '@handleAppSignupFinished',
        ],
        SubscriptionRenewed::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionRenewed',
        ]
    ];

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->commands(
            [
                SetMaropostTagsForExpiredUserProducts::class,
                IntercomReSyncTool::class,
                UserContentPermissionsResyncTool::class,
            ]
        );

        $this->publishes(
            [
                __DIR__ . '/../../config/event-data-synchronizer.php' => config_path('event-data-synchronizer.php'),
            ]
        );

        $this->app->instance(IntercomSyncServiceBase::class, $this->app->make(IntercomSyncService::class));
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