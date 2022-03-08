<?php

namespace Railroad\EventDataSynchronizer\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;
use Railroad\EventDataSynchronizer\Events\FirstActivityPerDay;
use Railroad\EventDataSynchronizer\Events\UTMLinks;

class UTMEventsMiddleware
{
    public static function handle($request, Closure $next)
    {
        if (auth()->check()) {
            $currentUser = auth()->user();

                //customer-io event
                event(
                    new UTMLinks(
                        $currentUser->getId(),
                        config('event-data-synchronizer.customer_io_brand_activity_event'),
                        Carbon::now()
                            ->toDateTimeString(),
                        $request->get('utm_id'),
                        $request->get('utm_source'),
                        $request->get('utm_campaign'),
                        $request->get('utm_medium')
                    )
                );

        }

        return $next($request);
    }
}