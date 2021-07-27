<?php

namespace Railroad\EventDataSynchronizer\Listeners\HelpScout;

use Carbon\Carbon;
use Railroad\EventDataSynchronizer\Jobs\HelpScoutNewUser;
use Railroad\EventDataSynchronizer\Jobs\HelpScoutUpdateUser;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;

class HelpScoutEventListener
{
    private $queueConnectionName = 'database';

    private $queueName = 'helpscout';

    /**
     * @var bool
     */
    public static $disable = false;

    /**
     * @var array
     */
    public static $alreadyQueuedUserIds = [];

    /**
     * HelpScoutEventListener constructor.
     *
     * @param  UserRepository  $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;

        $this->queueConnectionName = config('event-data-synchronizer.helpscout_queue_connection_name', 'database');
        $this->queueName = config('event-data-synchronizer.helpscout_queue_name', 'helpscout');
    }

    /**
     * @param  UserCreated  $userCreated
     */
    public function handleUserCreated(UserCreated $userCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty($user) && !in_array($userCreated->getUser()->getId(), self::$alreadyQueuedUserIds)) {

                $user = $this->userRepository->find($userCreated->getUser()->getId());

                dispatch(
                    (new HelpScoutNewUser($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $userCreated->getUser()->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserUpdated  $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty($user) && !in_array($userUpdated->getUser()->getId(), self::$alreadyQueuedUserIds)) {

                $user = $this->userRepository->find($userUpdated->getUser()->getId());

                dispatch(
                    (new HelpScoutUpdateUser($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $userUpdated->getUser()->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }
}
