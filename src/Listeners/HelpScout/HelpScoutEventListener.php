<?php

namespace Railroad\EventDataSynchronizer\Listeners\HelpScout;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\User as EcommerceUser;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Jobs\HelpScoutUpdateUser;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Entities\User;
use Railroad\Usora\Repositories\UserRepository;

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
            $user = $this->userRepository->find($userCreated->getUser()->getId());

            if (!empty($user) && !in_array($userCreated->getUser()->getId(), self::$alreadyQueuedUserIds)) {

                dispatch(
                    (new HelpScoutUpdateUser($user))
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
            $user = $this->userRepository->find($userUpdated->getNewUser()->getId());

            if (!empty($user) && !in_array($userUpdated->getNewUser()->getId(), self::$alreadyQueuedUserIds)) {

                dispatch(
                    (new HelpScoutUpdateUser($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $userUpdated->getNewUser()->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserProductCreated  $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find(
                $userProductCreated->getUserProduct()
                    ->getUser()
                    ->getId()
            );

            if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new HelpScoutUpdateUser($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserProductUpdated  $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find(
                $userProductUpdated->getNewUserProduct()
                    ->getUser()
                    ->getId()
            );

            if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new HelpScoutUpdateUser($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserProductDeleted  $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find(
                $userProductDeleted->getUserProduct()
                    ->getUser()
                    ->getId()
            );

            if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new HelpScoutUpdateUser($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionCreated  $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty(
                $subscriptionCreated->getSubscription()
                    ->getUser() &&
                $subscriptionCreated->getSubscription()
                    ->getUser() instanceof User
            )) {
                $user = $this->userRepository->find(
                    $subscriptionCreated->getSubscription()
                        ->getUser()
                        ->getId()
                );

                if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                    dispatch(
                        (new HelpScoutUpdateUser($user))
                            ->onConnection($this->queueConnectionName)
                            ->onQueue($this->queueName)
                            ->delay(Carbon::now()->addSeconds(3))
                    );

                    self::$alreadyQueuedUserIds[] = $user->getId();
                }
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionUpdated  $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        if (self::$disable) {
            return;
        }

        $newSubscription = $subscriptionUpdated->getNewSubscription();

        try {
            $user = $newSubscription->getUser();

            if ($user instanceof EcommerceUser) {
                $user = $this->userRepository->find($user->getId());
            }

            if ($user instanceof User) {
                if (!in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                    dispatch(
                        (new HelpScoutUpdateUser($user))
                            ->onConnection($this->queueConnectionName)
                            ->onQueue($this->queueName)
                            ->delay(Carbon::now()->addSeconds(3))
                    );

                    self::$alreadyQueuedUserIds[] = $user->getId();
                }
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionRenewed  $subscriptionRenewed
     */
    public function handleSubscriptionRenewed(SubscriptionRenewed $subscriptionRenewed)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty($subscriptionRenewed->getSubscription()) &&
                !empty($subscriptionRenewed->getSubscription()->getUser())) {
                
                $user = $subscriptionRenewed->getSubscription()->getUser();

                if ($user instanceof EcommerceUser) {
                    $user = $this->userRepository->find($user->getId());
                }

                if ($user instanceof User) {
                    if (!in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                        dispatch(
                            (new HelpScoutUpdateUser($user))
                                ->onConnection($this->queueConnectionName)
                                ->onQueue($this->queueName)
                                ->delay(Carbon::now()->addSeconds(3))
                        );

                        self::$alreadyQueuedUserIds[] = $user->getId();
                    }
                }

            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionRenewFailed  $subscriptionRenewFailed
     */
    public function handleSubscriptionRenewalAttemptFailed(SubscriptionRenewFailed $subscriptionRenewFailed)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty($subscriptionRenewFailed->getSubscription()) &&
                !empty($subscriptionRenewFailed->getSubscription()->getUser())) {
                
                $user = $subscriptionRenewFailed->getSubscription()->getUser();

                if ($user instanceof EcommerceUser) {
                    $user = $this->userRepository->find($user->getId());
                }

                if ($user instanceof User) {
                    if (!in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                        dispatch(
                            (new HelpScoutUpdateUser($user))
                                ->onConnection($this->queueConnectionName)
                                ->onQueue($this->queueName)
                                ->delay(Carbon::now()->addSeconds(3))
                        );

                        self::$alreadyQueuedUserIds[] = $user->getId();
                    }
                }

            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }
}
