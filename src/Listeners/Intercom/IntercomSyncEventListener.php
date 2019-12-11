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
        $this->handleUserUpdated(new UserUpdated($userCreated->getUser(), $userCreated->getUser()));
    }

    /**
     * @param  UserUpdated  $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
        $user = $userUpdated->getNewUser();

        if (!empty($user)) {
            $this->intercomSyncService->syncUsersAttributes($user);
        }
    }

    /**
     * @param  PaymentMethodCreated  $paymentMethodCreated
     */
    public function handleUserPaymentMethodCreated(PaymentMethodCreated $paymentMethodCreated)
    {
        if ($paymentMethodCreated->getUser() instanceof User) {
            $this->handleUserPaymentMethodUpdated(
                new PaymentMethodUpdated(
                    $paymentMethodCreated->getPaymentMethod(),
                    $paymentMethodCreated->getPaymentMethod(),
                    $paymentMethodCreated->getUser()
                )
            );
        }
    }

    /**
     * @param  PaymentMethodUpdated  $paymentMethodUpdated
     */
    public function handleUserPaymentMethodUpdated(PaymentMethodUpdated $paymentMethodUpdated)
    {
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
    }

    /**
     * @param  UserProductCreated  $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $user = $this->userRepository->find(
            $userProductCreated->getUserProduct()
                ->getUser()
                ->getId()
        );

        $this->intercomSyncService->syncUsersAttributes($user);
        $this->intercomSyncService->syncUsersProductOwnershipTags($user);
    }

    /**
     * @param  UserProductUpdated  $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $user = $this->userRepository->find(
            $userProductUpdated->getNewUserProduct()
                ->getUser()
                ->getId()
        );

        $this->intercomSyncService->syncUsersAttributes($user);
        $this->intercomSyncService->syncUsersProductOwnershipTags($user);
    }

    /**
     * @param  UserProductDeleted  $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        $user = $this->userRepository->find(
            $userProductDeleted->getUserProduct()
                ->getUser()
                ->getId()
        );

        $this->intercomSyncService->syncUsersAttributes($user);
        $this->intercomSyncService->syncUsersProductOwnershipTags($user);
    }

    /**
     * @param  SubscriptionCreated  $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        if (!empty(
        $subscriptionCreated->getSubscription()
            ->getUser() &&
        $subscriptionCreated->getSubscription()->getUser() instanceof User
        )) {
            $user = $this->userRepository->find(
                $subscriptionCreated->getSubscription()
                    ->getUser()
                    ->getId()
            );

            $this->intercomSyncService->syncUsersAttributes($user);
        }
    }

    /**
     * @param  SubscriptionUpdated  $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        if (!empty(
        $subscriptionUpdated->getNewSubscription()
            ->getUser() &&
        $subscriptionUpdated->getSubscription()->getUser() instanceof User
        )) {
            $user = $this->userRepository->find(
                $subscriptionUpdated->getNewSubscription()
                    ->getUser()
                    ->getId()
            );

            $this->intercomSyncService->syncUsersAttributes($user);
        }
    }

    /**
     * @param  AppSignupStartedEvent  $appSignupStarted
     */
    public function handleAppSignupStarted(AppSignupStartedEvent $appSignupStarted)
    {
        dispatch(new IntercomSyncUserByAttributes($appSignupStarted->getAttributes()));

        dispatch(
            new IntercomTagUserByAttributes(
                'drumeo_started_app_signup_flow', $appSignupStarted->getAttributes()
            )
        );
    }

    public function handleAppSignupFinished(AppSignupFinishedEvent $appSignupFinished)
    {
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
    }
}