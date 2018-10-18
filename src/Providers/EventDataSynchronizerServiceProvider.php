<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\Resora\Events\Created;
use Railroad\Resora\Events\Updated;

class EventDataSynchronizerServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Created::class => [
            UserProductToUserContentPermissionListener::class . '@' . 'handleCreated',
        ],
        Updated::class => [
            UserProductToUserContentPermissionListener::class . '@' . 'handleUpdated',
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