<?php

namespace Railroad\EventDataSynchronizer\Listeners\Intercom;

use Carbon\Carbon;
use Railroad\Ecommerce\Events\AppSignupFinishedEvent;
use Railroad\Ecommerce\Events\AppSignupStartedEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
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

class IntercomSyncEventListener
{
    /**
     * @var IntercomSyncServiceBase
     */
    private $intercomSyncService;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * IntercomSyncEventListener constructor.
     *
     * @param  IntercomSyncServiceBase  $intercomSyncService
     * @param  UserRepository  $userRepository
     */
    public function __construct(IntercomSyncServiceBase $intercomSyncService, UserRepository $userRepository)
    {
        $this->intercomSyncService = $intercomSyncService;
        $this->userRepository = $userRepository;
    }

    /**
     * @param  UserCreated  $userCreated
     */
    public function handleUserCreated(UserCreated $userCreated)
    {
        try {
            $this->handleUserUpdated(new UserUpdated($userCreated->getUser(), $userCreated->getUser()));
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserUpdated  $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
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
                        IntercomSyncServiceBase::$userIdPrefix .
                        $paymentMethodUpdated->getUser()
                            ->getId(),
                        $paymentMethodUpdated->getNewPaymentMethod()
                            ->getMethod()
                            ->getPaymentGatewayName() . '_payment_method_updated',
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
        try {
            if (!empty(
                $subscriptionUpdated->getNewSubscription()
                    ->getUser() &&
                $subscriptionUpdated->getNewSubscription()
                    ->getUser() instanceof User
            )) {
                $user = $this->userRepository->find(
                    $subscriptionUpdated->getNewSubscription()
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
     * @param  AppSignupStartedEvent  $appSignupStarted
     */
    public function handleAppSignupStarted(AppSignupStartedEvent $appSignupStarted)
    {
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

    public function handleAppSignupFinished(AppSignupFinishedEvent $appSignupFinished)
    {
        try {
            dispatch(
                new IntercomUnTagUserByAttributes(
                    'drumeo_started_app_signup_flow', ['email' => $appSignupFinished->getAttributes()['email']]
                )
            );

            dispatch(
                new IntercomSyncUserByAttributes(
                    [
                        'user_id' => config('event-data-synchronizer.intercom_user_id_prefix', 'musora_') .
                            $appSignupFinished->getAttributes()['user_id'],
                        'email' => $appSignupFinished->getAttributes()['email'],
                    ]
                )
            );
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }
}