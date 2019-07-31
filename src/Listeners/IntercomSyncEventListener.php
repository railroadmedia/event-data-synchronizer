<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\PaymentMethod;
use Railroad\Ecommerce\Entities\Subscription;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Intercomeo\Jobs\SyncUser;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;

// todo: this is only for pianote at the moment, in the future many changes are required for multiple brand support
class IntercomSyncEventListener
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;

    public function __construct(UserRepository $userRepository, SubscriptionRepository $subscriptionRepository)
    {
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;
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

            dispatch(
                new SyncUser(
                    $user->getId(), [
                        'email' => $user->getEmail(),
                        'created_at' => Carbon::parse($user->getCreatedAt())->timestamp,
                        'name' => $user->getFirstName() .
                            (!empty($user->getLastName()) ? ' ' . $user->getLastName() : ''),
                        'avatar' => ['type' => 'avatar', 'image_url' => $user->getProfilePictureUrl()],
                        'custom_attributes' => [
                            'pianote_user' => true,
                            'pianote_display_name' => $user->getDisplayName(),
                            'pianote_birthday' => !empty($user->getBirthday()) ? Carbon::parse(
                                $user->getBirthday()
                            )->timestamp : null,
                        ],
                    ]
                )
            );

        }
    }

    /**
     * @param PaymentMethodCreated $paymentMethodCreated
     */
    public function handleUserPaymentMethodCreated(PaymentMethodCreated $paymentMethodCreated)
    {
        $this->handleUserPaymentMethodUpdated(
            new PaymentMethodUpdated(
                $paymentMethodCreated->getPaymentMethod(), $paymentMethodCreated->getPaymentMethod()
            )
        );
    }

    /**
     * @param PaymentMethodUpdated $paymentMethodUpdated
     */
    public function handleUserPaymentMethodUpdated(PaymentMethodUpdated $paymentMethodUpdated)
    {
        $userPaymentMethod =
            $paymentMethodUpdated->getNewPaymentMethod()
                ->getUserPaymentMethod();

        if (!empty($userPaymentMethod) && !empty($userPaymentMethod->getUser())) {
            if ($paymentMethodUpdated->getNewPaymentMethod()
                    ->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD) {
                $expirationDate = Carbon::parse(
                    $paymentMethodUpdated->getNewPaymentMethod()
                        ->getMethod()
                        ->getExpirationDate()
                )->timestamp;
            }
            else {
                $expirationDate = null;
            }

            dispatch(
                new SyncUser(
                    $userPaymentMethod->getUser()
                        ->getId(), [
                        'custom_attributes' => [
                            'pianote_primary_payment_method_expiration_date' => $expirationDate,
                        ],
                    ]
                )
            );
        }
    }

    /**
     * @param UserProductCreated $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $this->handleUserProductUpdated(
            new UserProductUpdated($userProductCreated->getUserProduct(), $userProductCreated->getUserProduct())
        );
    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        // todo: bug, this should also sync the furthest active user product in the array

        if (in_array(
            $userProductUpdated->getNewUserProduct()
                ->getProduct()
                ->getId(),
            config('event-data-synchronizer.pianote_membership_product_ids')
        )) {

            $data = [
                'pianote_membership_access_expiration_date' => !empty(
                $userProductUpdated->getNewUserProduct()
                    ->getExpirationDate()
                ) ? Carbon::parse(
                    $userProductUpdated->getNewUserProduct()
                        ->getExpirationDate()
                )->timestamp : null,
            ];

            if ($userProductUpdated->getNewUserProduct()
                    ->getExpirationDate() == null) {
                $data['pianote_is_lifetime_member'] = true;
            }

            dispatch(
                new SyncUser(
                    $userProductUpdated->getNewUserProduct()
                        ->getUser()
                        ->getId(), ['custom_attributes' => $data]
                )
            );

        }

        if ($userProductUpdated->getNewUserProduct()
                ->getProduct()
                ->getSku() == '500-songs-in-5-days' ||
            $userProductUpdated->getNewUserProduct()
                ->getProduct()
                ->getSku() == '500-songs-in-5-days-99') {

            if ($userProductUpdated->getNewUserProduct()
                    ->getExpirationDate() == null ||
                $userProductUpdated->getNewUserProduct()
                    ->getExpirationDate() > Carbon::now()) {
                dispatch(
                    new SyncUser(
                        $userProductUpdated->getNewUserProduct()
                            ->getUser()
                            ->getId(), [
                            'custom_attributes' => [
                                'pianote_500_songs_in_5_days_pack_owner' => true,
                            ]
                        ]
                    )
                );
            }
            else {
                dispatch(
                    new SyncUser(
                        $userProductUpdated->getNewUserProduct()
                            ->getUser()
                            ->getId(), [
                            'custom_attributes' => [
                                'pianote_500_songs_in_5_days_pack_owner' => null,
                            ]
                        ]
                    )
                );
            }

        }

    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        // todo: bug, this should also sync the furthest active user product in the array

        if (in_array(
            $userProductDeleted->getUserProduct()
                ->getProduct()
                ->getId(),
            config('event-data-synchronizer.pianote_membership_product_ids')
        )) {

            dispatch(
                new SyncUser(
                    $userProductDeleted->getUserProduct()
                        ->getUser()
                        ->getId(), ['custom_attributes' => ['pianote_membership_access_expiration_date' => null,],]
                )
            );

        }

        if ($userProductDeleted->getUserProduct()
                ->getProduct()
                ->getSku() == '500-songs-in-5-days') {

            dispatch(
                new SyncUser(
                    $userProductDeleted->getUserProduct()
                        ->getUser()
                        ->getId(), [
                        'custom_attributes' => [
                            'pianote_500_songs_in_5_days_pack_owner' => null,
                        ]
                    ]
                )
            );

        }
    }

    /**
     * @param SubscriptionCreated $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        $this->handleSubscriptionUpdated(
            new SubscriptionUpdated($subscriptionCreated->getSubscription(), $subscriptionCreated->getSubscription())
        );
    }

    /**
     * @param SubscriptionUpdated $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        $subscription = $subscriptionUpdated->getNewSubscription();

        $user = $this->userRepository->find(
            $subscription->getUser()
                ->getId()
        );

        $allSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions(
            $subscription->getUser()
                ->getId(),
            config('event-data-synchronizer.pianote_membership_product_ids')
        );

        /**
         * @var $activeSubscription Subscription|null
         */
        $activeSubscription = null;

        /**
         * @var $mostRecentSubscription Subscription|null
         */
        $mostRecentSubscription = null;

        foreach ($allSubscriptions as $userSubscription) {
            if (empty($userSubscription->getProduct())) {
                continue;
            }

            if (Carbon::parse($userSubscription->getPaidUntil()) > Carbon::now() && $userSubscription->getIsActive()) {

                if (is_null($activeSubscription) ||
                    Carbon::parse($userSubscription->getPaidUntil()) >
                    Carbon::parse($activeSubscription->getPaidUntil())) {

                    $activeSubscription = $userSubscription;
                }

            }
            else {

                if (is_null($mostRecentSubscription) ||
                    Carbon::parse($userSubscription->getPaidUntil()) >
                    Carbon::parse($mostRecentSubscription->getPaidUntil())) {

                    $mostRecentSubscription = $userSubscription;
                }

            }
        }

        $totalPaymentsOnActiveSubscription = null;
        $subscriptionStatus = null;

        if (!empty($activeSubscription)) {

            $totalPaymentsOnActiveSubscription = 0;

            foreach ($activeSubscription->getPayments() as $_payment) {
                if ($_payment->getTotalPaid() == $_payment->getTotalDue()) {
                    $totalPaymentsOnActiveSubscription++;
                }
            }

            $membershipRenewalDate = Carbon::parse($activeSubscription->getPaidUntil())->timestamp;
            $paymentMethodExpirationDate =
                $activeSubscription->getPaymentMethod()
                    ->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD ? Carbon::parse(
                    $activeSubscription->getPaymentMethod()
                        ->getMethod()
                        ->getExpirationDate()
                )->timestamp : null;
            $membershipCancellationDate = null;
            $subscriptionStatus = $activeSubscription->getIsActive();
            $subscriptionStartedDate = Carbon::parse($activeSubscription->getCreatedAt())->timestamp;

        }
        elseif (!empty($mostRecentSubscription)) {

            $totalPaymentsOnActiveSubscription = 0;

            foreach ($mostRecentSubscription->getPayments() as $_payment) {
                if ($_payment->getTotalPaid() == $_payment->getTotalDue()) {
                    $totalPaymentsOnActiveSubscription++;
                }
            }

            $membershipRenewalDate = null;
            $paymentMethodExpirationDate = null;
            $membershipCancellationDate = Carbon::parse($mostRecentSubscription->getCanceledOn())->timestamp ?? null;
            $subscriptionStatus = $mostRecentSubscription->getIsActive();
            $subscriptionStartedDate = Carbon::parse($mostRecentSubscription->getCreatedAt())->timestamp;

        }
        else {
            $membershipRenewalDate = null;
            $paymentMethodExpirationDate = null;
            $membershipCancellationDate = null;
            $subscriptionStatus = null;
            $subscriptionStartedDate = null;
        }

        $subscriptionProductTag = null;

        if (!empty($activeSubscription)) {
            $subscriptionProductTag =
                $activeSubscription->getIntervalCount() . '_' . $activeSubscription->getIntervalType();

        }

        dispatch(
            new SyncUser(
                $subscription->getUser()
                    ->getId(), [
                    'custom_attributes' => [
                        'pianote_membership_subscription_status' => $subscriptionStatus ? 'active' : 'suspended',
                        'pianote_membership_subscription_type' => $subscriptionProductTag,
                        'pianote_membership_subscription_renewal_date' => $membershipRenewalDate,
                        'pianote_primary_payment_method_expiration_date' => $paymentMethodExpirationDate,
                        'pianote_membership_subscription_cancellation_date' => $membershipCancellationDate,
                        'pianote_membership_subscription_started_date' => $subscriptionStartedDate,
                    ],
                ]
            )
        );
    }
}