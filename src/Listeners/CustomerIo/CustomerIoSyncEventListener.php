<?php

namespace Railroad\EventDataSynchronizer\Listeners\CustomerIo;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\User as EcommerceUser;
use Railroad\Ecommerce\Events\AppSignupFinishedEvent;
use Railroad\Ecommerce\Events\AppSignupStartedEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Jobs\CustomerIoSyncNewUserByEmail;
use Railroad\EventDataSynchronizer\Services\IntercomSyncServiceBase;
use Railroad\Intercomeo\Jobs\IntercomSyncUserByAttributes;
use Railroad\Intercomeo\Jobs\IntercomTagUserByAttributes;
use Railroad\Intercomeo\Jobs\IntercomTriggerEventForUser;
use Railroad\Intercomeo\Jobs\IntercomUnTagUserByAttributes;
use Railroad\Usora\Entities\User;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;
use Throwable;

class CustomerIoSyncEventListener
{
    /**
     * @var IntercomSyncServiceBase
     */
    private $intercomSyncService;

    /**
     * @var UserRepository
     */
    private $userRepository;

    private $queueConnectionName = 'database';
    private $queueName = 'customer_io';

    /**
     * @var bool
     */
    public static $disable = false;

    /**
     * CustomerIoSyncEventListener constructor.
     *
     * @param  IntercomSyncServiceBase  $intercomSyncService
     * @param  UserRepository  $userRepository
     */
    public function __construct(IntercomSyncServiceBase $intercomSyncService, UserRepository $userRepository)
    {
        $this->intercomSyncService = $intercomSyncService;
        $this->userRepository = $userRepository;

        $this->queueConnectionName = config('event-data-synchronizer.customer_io_queue_connection_name', 'database');
        $this->queueName = config('event-data-synchronizer.customer_io_queue_name', 'customer_io');
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
            dispatch(
                (new CustomerIoSyncNewUserByEmail($userCreated->getUser()))
                    ->onConnection($this->queueConnectionName)
                    ->onQueue($this->queueName)
            );
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
            $user = $userUpdated->getNewUser();

            if (!empty($user)) {
                $this->intercomSyncService->syncUsersAttributes($user);
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  PaymentMethodCreated  $paymentMethodCreated
     */
    public function handleUserPaymentMethodCreated(PaymentMethodCreated $paymentMethodCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if ($paymentMethodCreated->getUser() instanceof User) {
                $this->handleUserPaymentMethodUpdated(
                    new PaymentMethodUpdated(
                        $paymentMethodCreated->getPaymentMethod(),
                        $paymentMethodCreated->getPaymentMethod(),
                        $paymentMethodCreated->getUser()
                    )
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  PaymentMethodUpdated  $paymentMethodUpdated
     */
    public function handleUserPaymentMethodUpdated(PaymentMethodUpdated $paymentMethodUpdated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty(
                $paymentMethodUpdated->getUser()
                    ->getId()
                ) && $paymentMethodUpdated->getUser() instanceof User) {
                $user = $this->userRepository->find(
                    $paymentMethodUpdated->getUser()
                        ->getId()
                );

                $this->intercomSyncService->syncUsersAttributes($user);

                dispatch(
                    new IntercomTriggerEventForUser(
                        IntercomSyncServiceBase::$userIdPrefix.
                        $paymentMethodUpdated->getUser()
                            ->getId(),
                        $paymentMethodUpdated->getNewPaymentMethod()
                            ->getMethod()
                            ->getPaymentGatewayName().'_payment_method_updated',
                        Carbon::now()
                            ->toDateTimeString()
                    )
                );
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

            $this->intercomSyncService->syncUsersAttributes($user);
            $this->intercomSyncService->syncUsersProductOwnershipTags($user);
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

            $this->intercomSyncService->syncUsersAttributes($user);
            $this->intercomSyncService->syncUsersProductOwnershipTags($user);
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

            $this->intercomSyncService->syncUsersAttributes($user);
            $this->intercomSyncService->syncUsersProductOwnershipTags($user);
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

                $this->intercomSyncService->syncUsersAttributes($user);
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
                $user = $this->userRepository->find($newSubscription->getUser()->getId());
            }

            if ($user instanceof User) {
                $this->intercomSyncService->syncUsersAttributes($user);
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  AppSignupStartedEvent  $appSignupStarted
     */
    public function handleAppSignupStarted(AppSignupStartedEvent $appSignupStarted)
    {
        if (self::$disable) {
            return;
        }

        try {
            dispatch(new IntercomSyncUserByAttributes($appSignupStarted->getAttributes()));

            dispatch(
                new IntercomTagUserByAttributes(
                    'drumeo_started_app_signup_flow', $appSignupStarted->getAttributes()
                )
            );
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  AppSignupFinishedEvent  $appSignupFinished
     */
    public function handleAppSignupFinished(AppSignupFinishedEvent $appSignupFinished)
    {
        if (self::$disable) {
            return;
        }

        try {
            dispatch(
                new IntercomUnTagUserByAttributes(
                    'drumeo_started_app_signup_flow', ['email' => $appSignupFinished->getAttributes()['email']]
                )
            );

            dispatch(
                new IntercomSyncUserByAttributes(
                    [
                        'user_id' => config('event-data-synchronizer.intercom_user_id_prefix', 'musora_').
                            $appSignupFinished->getAttributes()['user_id'],
                        'email' => $appSignupFinished->getAttributes()['email'],
                    ]
                )
            );
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
                dispatch(
                    new IntercomTriggerEventForUser(
                        IntercomSyncServiceBase::$userIdPrefix.
                        $subscriptionRenewed->getSubscription()->getUser()->getId(),
                        $subscriptionRenewed->getSubscription()->getBrand().'_membership_renewed',
                        Carbon::now()
                            ->toDateTimeString()
                    )
                );
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

        if ($subscriptionRenewFailed->getSubscription()->getIsActive() == false &&
            $subscriptionRenewFailed->getSubscription()->getCanceledOn() == null &&
            $subscriptionRenewFailed->getSubscription()->getStopped() == false &&
            $subscriptionRenewFailed->getSubscription()->getPaidUntil() < Carbon::now()) {
            dispatch(
                new IntercomSyncUserByAttributes(
                    [
                        'user_id' => config('event-data-synchronizer.intercom_user_id_prefix', 'musora_').
                            $subscriptionRenewFailed->getSubscription()->getUser()->getId(),
                        'custom_attributes' => [
                            $subscriptionRenewFailed->getSubscription()->getBrand().'_membership_renewal_attempt' =>
                                $subscriptionRenewFailed->getSubscription()->getRenewalAttempt(),
                        ],
                    ]
                )
            );
        }
    }
}