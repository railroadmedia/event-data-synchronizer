<?php

namespace Railroad\EventDataSynchronizer\Middleware;

// todo: implement this so we only send 1 request to update a user even though multiple events may have triggered
// multiple attribute changes

class IntercomSyncMiddleware
{
    public static function queueCustomAttributes($userId, array $attributes)
    {
        // merge with existing queue attributes for this user
    }

    public static function sync()
    {
        // actually send the intercom request if there are any attributes to be updated
    }
}