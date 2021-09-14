<?php

namespace Railroad\EventDataSynchronizer\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;

class UserActivitySyncMiddleware
{
    public static function handle($request, Closure $next)
    {

        if (auth()->check()) {
            $currentUser = auth()->user();
            $redis = Cache::store('redis')
                ->connection();
            $eventName = config('event-data-synchronizer.customer_io_brand_activity_event'). '_'.$currentUser->getId();

            if (!$redis->exists($eventName)) {
                $redis->set(
                        $eventName,
                        Carbon::now()
                            ->toDateTimeString(),
                        'EX',
                        20
                    );

                //sync customer-io attributes
            }
        }

        return $next($request);
    }
}