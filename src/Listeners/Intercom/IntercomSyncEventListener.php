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
use Railroad\EventDataSynchronizer\Services\IntercomSyncService;
use Railroad\Intercomeo\Jobs\IntercomSyncUserByAttributes;
use Railroad\Intercomeo\Jobs\IntercomTagUserByAttributes;
use Railroad\Intercomeo\Jobs\IntercomTriggerEventForUser;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;

class IntercomSyncEventListener
{
    /**
     * @var IntercomSyncService
     */
    private $intercomSyncService;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * IntercomSyncEventListener constructor.
     *
     * @param IntercomSyncService $intercomSyncService
     * @param UserRepository $userRepository
     */
    public function __construct(IntercomSyncService $intercomSyncService, UserRepository $userRepository)
    {
        $this->intercomSyncService = $intercomSyncService;
        $this->userRepository = $userRepository;
    }

    /**
     * @param UserCreated $userCreated
     */
    public function handleUserCreated(UserCreated $userCreated)
    {
        $this->handleUserUpdated(new UserUpdated($userCreated->getUser(), $userCreated->getUser()));
    }

    /**
     * @param UserUpdated $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
        $user = $userUpdated->getNewUser();

        if (!empty($user)) {
            $this->intercomSyncService->syncUsersAttributes($user);
        }
    }

    /**
     * @param PaymentMethodCreated $paymentMethodCreated
     */
    public function handleUserPaymentMethodCreated(PaymentMethodCreated $paymentMethodCreated)
    {
        $this->handleUserPaymentMethodUpdated(
            new PaymentMethodUpdated(
                $paymentMethodCreated->getPaymentMethod(),
                $paymentMethodCreated->getPaymentMethod(),
                $paymentMethodCreated->getUser()
            )
        );
    }

    /**
     * @param PaymentMethodUpdated $paymentMethodUpdated
     */
    public function handleUserPaymentMethodUpdated(PaymentMethodUpdated $paymentMethodUpdated)
    {
        if (!empty(
        $paymentMethodUpdated->getUser()
            ->getId()
        )) {
            $user = $this->userRepository->find(
                $paymentMethodUpdated->getUser()
                    ->getId()
            );

            $this->intercomSyncService->syncUsersAttributes($user);

            dispatch(
                new IntercomTriggerEventForUser(
                    IntercomSyncService::$userIdPrefix .
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
     * @param UserProductCreated $userProductCreated
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
     * @param UserProductUpdated $userProductUpdated
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
     * @param UserProductDeleted $userProductDeleted
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
     * @param SubscriptionCreated $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        $user = $this->userRepository->find(
            $subscriptionCreated->getSubscription()
                ->getUser()
                ->getId()
        );

        $this->intercomSyncService->syncUsersAttributes($user);
    }

    /**
     * @param SubscriptionUpdated $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        $user = $this->userRepository->find(
            $subscriptionUpdated->getNewSubscription()
                ->getUser()
                ->getId()
        );

        $this->intercomSyncService->syncUsersAttributes($user);
    }

    /**
     * @param AppSignupStartedEvent $appSignupStarted
     */
    public function handleAppSignupStarted(AppSignupStartedEvent $appSignupStarted)
    {
        dispatch(new IntercomSyncUserByAttributes($appSignupStarted->getAttributes()));

        dispatch(
            new IntercomTagUserByAttributes(
                $appSignupStarted->getAttributes()['brand'] . '_started_app_signup_flow',
                $appSignupStarted->getAttributes()
            )
        );
    }

    /**
     * @param AppSignupFinishedEvent $appSignupFinished
     */
    public function handleAppSignupFinished(AppSignupFinishedEvent $appSignupFinished)
    {
        dispatch(
            new IntercomSyncUserByAttributes(
                $appSignupFinished->getAttributes()['user_id'], [
                    'email' => $appSignupFinished->getAttributes()['email'],
                ]
            )
        );

        dispatch(
            new IntercomTagUserByAttributes(
                [$appSignupFinished->getAttributes()['user_id']],
                $appSignupFinished->getAttributes()['brand'] . '_started_app_signup_flow'
            )
        );
    }
}