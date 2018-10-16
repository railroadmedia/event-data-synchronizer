<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Railroad\Ecommerce\Events\SubscriptionEvent;
use Railroad\EventDataSynchronizer\Listeners\UserSubscriptionToUserContentPermissionListener;

class EventDataSynchronizerServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        SubscriptionEvent::class => [
            UserSubscriptionToUserContentPermissionListener::class . '@' . 'handle',
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