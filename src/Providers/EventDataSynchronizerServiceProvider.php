<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Railroad\Ecommerce\Events\AppSignupFinishedEvent;
use Railroad\Ecommerce\Events\AppSignupStartedEvent;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\PaymentEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Console\Commands\HelpScoutIndex;
use Railroad\EventDataSynchronizer\Console\Commands\IntercomReSyncTool;
use Railroad\EventDataSynchronizer\Console\Commands\SetMaropostTagsForExpiredUserProducts;
use Railroad\EventDataSynchronizer\Console\Commands\SyncCustomerIoForUpdatedUserProductsAndSubscriptions;
use Railroad\EventDataSynchronizer\Console\Commands\SyncCustomerIoOldEvents;
use Railroad\EventDataSynchronizer\Console\Commands\SyncExistingHelpScout;
use Railroad\EventDataSynchronizer\Console\Commands\SyncHelpScout;
use Railroad\EventDataSynchronizer\Console\Commands\SyncHelpScoutAsync;
use Railroad\EventDataSynchronizer\Console\Commands\UserContentPermissionsResyncTool;
use Railroad\EventDataSynchronizer\Events\FirstActivityPerDay;
use Railroad\EventDataSynchronizer\Events\LiveStreamEventAttended;
use Railroad\EventDataSynchronizer\Events\UTMLinks;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\Impact\ImpactEventListener;
use Railroad\EventDataSynchronizer\Listeners\HelpScout\HelpScoutEventListener;
use Railroad\EventDataSynchronizer\Listeners\Intercom\IntercomSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\EventDataSynchronizer\Services\IntercomSyncService;
use Railroad\EventDataSynchronizer\Services\IntercomSyncServiceBase;
use Railroad\Maropost\Providers\MaropostServiceProvider;
use Railroad\Railcontent\Events\CommentCreated;
use Railroad\Railcontent\Events\CommentLiked;
use Railroad\Railcontent\Events\UserContentProgressSaved;
use Railroad\Railforums\Events\PostCreated;
use Railroad\Railforums\Events\ThreadCreated;
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
            CustomerIoSyncEventListener::class . '@handleUserCreated',
            HelpScoutEventListener::class . '@handleUserCreated',
        ],
        UserUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserUpdated',
            CustomerIoSyncEventListener::class . '@handleUserUpdated',
            HelpScoutEventListener::class . '@handleUserUpdated',
        ],
        PaymentMethodCreated::class => [
            IntercomSyncEventListener::class . '@handleUserPaymentMethodCreated',
            CustomerIoSyncEventListener::class . '@handleUserPaymentMethodCreated',
        ],
        PaymentMethodUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserPaymentMethodUpdated',
            CustomerIoSyncEventListener::class . '@handleUserPaymentMethodUpdated',
        ],
        UserProductCreated::class => [
            IntercomSyncEventListener::class . '@handleUserProductCreated',
            CustomerIoSyncEventListener::class . '@handleUserProductCreated',

            UserProductToUserContentPermissionListener::class . '@handleCreated',
            HelpScoutEventListener::class . '@handleUserProductCreated',
        ],
        UserProductUpdated::class => [
            IntercomSyncEventListener::class . '@handleUserProductUpdated',
            CustomerIoSyncEventListener::class . '@handleUserProductUpdated',

            UserProductToUserContentPermissionListener::class . '@handleUpdated',
            HelpScoutEventListener::class . '@handleUserProductUpdated',
        ],
        UserProductDeleted::class => [
            IntercomSyncEventListener::class . '@handleUserProductDeleted',
            CustomerIoSyncEventListener::class . '@handleUserProductDeleted',

            UserProductToUserContentPermissionListener::class . '@handleDeleted',
            HelpScoutEventListener::class . '@handleUserProductDeleted',
        ],
        SubscriptionCreated::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionCreated',
            CustomerIoSyncEventListener::class . '@handleSubscriptionCreated',

            HelpScoutEventListener::class . '@handleSubscriptionCreated',
        ],
        SubscriptionUpdated::class => [
            IntercomSyncEventListener::class . '@handleSubscriptionUpdated',
            CustomerIoSyncEventListener::class . '@handleSubscriptionUpdated',

            HelpScoutEventListener::class . '@handleSubscriptionUpdated',
        ],
        SubscriptionRenewed::class => [
            CustomerIoSyncEventListener::class . '@handleSubscriptionRenewed',
            IntercomSyncEventListener::class . '@handleSubscriptionRenewed',
            HelpScoutEventListener::class . '@handleSubscriptionRenewed',
        ],
        SubscriptionRenewFailed::class => [
            CustomerIoSyncEventListener::class . '@handleSubscriptionRenewalAttemptFailed',
            IntercomSyncEventListener::class . '@handleSubscriptionRenewalAttemptFailed',
            HelpScoutEventListener::class . '@handleSubscriptionRenewalAttemptFailed',
        ],
        OrderEvent::class => [
            CustomerIoSyncEventListener::class . '@handleOrderPlaced',
            ImpactEventListener::class . '@handleOrderPlaced',
        ],
        PaymentEvent::class => [
            CustomerIoSyncEventListener::class . '@handlePaymentPaid',
        ],

        AppSignupStartedEvent::class => [
            CustomerIoSyncEventListener::class . '@handleAppSignupStarted',
            IntercomSyncEventListener::class . '@handleAppSignupStarted',
        ],
        AppSignupFinishedEvent::class => [
            CustomerIoSyncEventListener::class . '@handleAppSignupFinished',
            IntercomSyncEventListener::class . '@handleAppSignupFinished',
        ],
        CommentLiked::class => [
            CustomerIoSyncEventListener::class . '@handleCommentLiked',
        ],
        CommentCreated::class => [
            CustomerIoSyncEventListener::class . '@handleCommentCreated',
        ],
        ThreadCreated::class => [
            CustomerIoSyncEventListener::class . '@handleForumsThreadCreated',
        ],
        PostCreated::class => [
            CustomerIoSyncEventListener::class . '@handleForumsPostCreated',
        ],
        UserContentProgressSaved::class => [
            CustomerIoSyncEventListener::class . '@handleUserContentProgressSaved',
        ],
        LiveStreamEventAttended::class => [
            CustomerIoSyncEventListener::class . '@handleLiveLessonAttended',
        ],
        FirstActivityPerDay::class => [
            CustomerIoSyncEventListener::class . '@handleFirstActivityPerDay',
        ],
        UTMLinks::class => [
//            CustomerIoSyncEventListener::class . '@handleUTMLinks',
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
                SyncCustomerIoForUpdatedUserProductsAndSubscriptions::class,
                SetMaropostTagsForExpiredUserProducts::class,
                IntercomReSyncTool::class,
                UserContentPermissionsResyncTool::class,
                SyncHelpScout::class,
                SyncExistingHelpScout::class,
                HelpScoutIndex::class,
                SyncHelpScoutAsync::class,
                SyncCustomerIoOldEvents::class
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