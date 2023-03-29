<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Railroad\Ecommerce\Events\AccessCodeClaimed;
use Railroad\Ecommerce\Events\AppSignupFinishedEvent;
use Railroad\Ecommerce\Events\AppSignupStartedEvent;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\PaymentEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\RefundEvent;
use Railroad\Ecommerce\Events\Subscriptions\CommandSubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Console\Commands\HelpScoutIndex;
use Railroad\EventDataSynchronizer\Console\Commands\PackOwnerUserFieldResyncTool;
use Railroad\EventDataSynchronizer\Console\Commands\SyncCustomerIoExistingDevices;
use Railroad\EventDataSynchronizer\Console\Commands\SyncCustomerIoForUpdatedUserProductsAndSubscriptions;
use Railroad\EventDataSynchronizer\Console\Commands\SyncCustomerIoOldEvents;
use Railroad\EventDataSynchronizer\Console\Commands\SyncExistingHelpScout;
use Railroad\EventDataSynchronizer\Console\Commands\SyncHelpScout;
use Railroad\EventDataSynchronizer\Console\Commands\SyncHelpScoutAsync;
use Railroad\EventDataSynchronizer\Console\Commands\UserContentPermissionsResyncTool;
use Railroad\EventDataSynchronizer\Console\Commands\UserMembershipFieldsResyncTool;
use Railroad\EventDataSynchronizer\Events\FirstActivityPerDay;
use Railroad\EventDataSynchronizer\Events\LiveStreamEventAttended;
use Railroad\EventDataSynchronizer\Events\UTMLinks;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;
use Railroad\EventDataSynchronizer\Listeners\HelpScout\HelpScoutEventListener;
use Railroad\EventDataSynchronizer\Listeners\UserMembershipFieldsListener;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\Railcontent\Events\CommentCreated;
use Railroad\Railcontent\Events\CommentLiked;
use Railroad\Railcontent\Events\ContentFollow;
use Railroad\Railcontent\Events\ContentUnfollow;
use Railroad\Railcontent\Events\UserContentProgressSaved;
use Railroad\Railforums\Events\PostCreated;
use Railroad\Railforums\Events\ThreadCreated;
use Railroad\Usora\Events\MobileAppLogin;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Referral\Events\EmailInvite;

class EventDataSynchronizerServiceProvider extends EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        UserCreated::class => [
            CustomerIoSyncEventListener::class . '@handleUserCreated',
            HelpScoutEventListener::class . '@handleUserCreated',
        ],
        UserUpdated::class => [
            CustomerIoSyncEventListener::class . '@handleUserUpdated',
            HelpScoutEventListener::class . '@handleUserUpdated',
        ],
        PaymentMethodCreated::class => [
            CustomerIoSyncEventListener::class . '@handleUserPaymentMethodCreated',
        ],
        PaymentMethodUpdated::class => [
            CustomerIoSyncEventListener::class . '@handleUserPaymentMethodUpdated',
        ],
        UserProductCreated::class => [
            CustomerIoSyncEventListener::class . '@handleUserProductCreated',
            UserProductToUserContentPermissionListener::class . '@handleCreated',
            HelpScoutEventListener::class . '@handleUserProductCreated',
            UserMembershipFieldsListener::class . '@handleUserProductCreated',
        ],
        UserProductUpdated::class => [
            CustomerIoSyncEventListener::class . '@handleUserProductUpdated',
            UserProductToUserContentPermissionListener::class . '@handleUpdated',
            HelpScoutEventListener::class . '@handleUserProductUpdated',
            UserMembershipFieldsListener::class . '@handleUserProductUpdated',
        ],
        UserProductDeleted::class => [
            CustomerIoSyncEventListener::class . '@handleUserProductDeleted',
            UserProductToUserContentPermissionListener::class . '@handleDeleted',
            HelpScoutEventListener::class . '@handleUserProductDeleted',
            UserMembershipFieldsListener::class . '@handleUserProductDeleted',
        ],
        SubscriptionCreated::class => [
            CustomerIoSyncEventListener::class . '@handleSubscriptionCreated',
            HelpScoutEventListener::class . '@handleSubscriptionCreated',
        ],
        SubscriptionUpdated::class => [
            CustomerIoSyncEventListener::class . '@handleSubscriptionUpdated',
            HelpScoutEventListener::class . '@handleSubscriptionUpdated',
        ],
        SubscriptionRenewed::class => [
            CustomerIoSyncEventListener::class . '@handleSubscriptionRenewed',
            HelpScoutEventListener::class . '@handleSubscriptionRenewed',
        ],
        SubscriptionRenewFailed::class => [
            CustomerIoSyncEventListener::class . '@handleSubscriptionRenewalAttemptFailed',
            HelpScoutEventListener::class . '@handleSubscriptionRenewalAttemptFailed',
        ],
        OrderEvent::class => [
            CustomerIoSyncEventListener::class . '@handleOrderPlaced',
        ],
        PaymentEvent::class => [
            CustomerIoSyncEventListener::class . '@handlePaymentPaid',
        ],

        AppSignupStartedEvent::class => [
            CustomerIoSyncEventListener::class . '@handleAppSignupStarted',
        ],
        AppSignupFinishedEvent::class => [
            CustomerIoSyncEventListener::class . '@handleAppSignupFinished',
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
        ],
        MobileAppLogin::class => [
            CustomerIoSyncEventListener::class . '@handleMobileAppLogin',
        ],
        ContentFollow::class => [
            CustomerIoSyncEventListener::class . '@handleContentFollow',
        ],
        ContentUnfollow::class => [
            CustomerIoSyncEventListener::class . '@handleContentUnfollow',
        ],
        EmailInvite::class => [
            CustomerIoSyncEventListener::class . '@handleReferralInvite',
        ],
        CommandSubscriptionRenewFailed::class => [
            UserProductToUserContentPermissionListener::class . '@handleSubscriptionRenewalFailureFromDatabaseError'
        ],
        AccessCodeClaimed::class => [
            CustomerIoSyncEventListener::class . '@handleAccessCodeClaimed',
        ],
        RefundEvent::class => [
            CustomerIoSyncEventListener::class . '@handleRefund',
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

        $this->commands(
            [
                SyncCustomerIoForUpdatedUserProductsAndSubscriptions::class,
                UserContentPermissionsResyncTool::class,
                SyncHelpScout::class,
                SyncExistingHelpScout::class,
                HelpScoutIndex::class,
                SyncHelpScoutAsync::class,
                SyncCustomerIoOldEvents::class,
                SyncCustomerIoExistingDevices::class,
                UserMembershipFieldsResyncTool::class,
                PackOwnerUserFieldResyncTool::class,
            ]
        );

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
        parent::register();
    }
}
