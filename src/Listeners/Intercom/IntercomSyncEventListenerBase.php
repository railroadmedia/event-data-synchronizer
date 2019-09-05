<?php

namespace Railroad\EventDataSynchronizer\Listeners\Intercom;

use Carbon\Carbon;
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

class IntercomSyncEventListenerBase
{
    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var SubscriptionRepository
     */
    protected $subscriptionRepository;

    /**
     * @var PianoteIntercomSyncEventListener
     */
    private $pianoteIntercomSyncEventListener;

    /**
     * @var GuitareoIntercomSyncEventListener
     */
    private $guitareoIntercomSyncEventListener;

    public function __construct(
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        PianoteIntercomSyncEventListener $pianoteIntercomSyncEventListener,
        GuitareoIntercomSyncEventListener $guitareoIntercomSyncEventListener
    )
    {
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;

        $this->pianoteIntercomSyncEventListener = $pianoteIntercomSyncEventListener;
        $this->guitareoIntercomSyncEventListener = $guitareoIntercomSyncEventListener;
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
                            'display_name' => $user->getDisplayName(),
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
        $paymentMethod = $paymentMethodUpdated->getNewPaymentMethod();

        if (!empty($paymentMethod) && !empty($paymentMethodUpdated->getUser())) {

            // get all subscriptions associated with the payment method
            $subscriptions = $this->subscriptionRepository->getPaymentMethodSubscriptions(
                $paymentMethod
            );

            foreach ($subscriptions as $subscription) {
                // todo when payment method editing is done
            }

            //            if ($paymentMethod->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD) {
            //
            //                $expirationDate = Carbon::parse(
            //                    $paymentMethod->getMethod()
            //                        ->getExpirationDate()
            //                )->timestamp;
            //            }
            //            else {
            //                $expirationDate = null;
            //            }
            //
            //            dispatch(
            //                new SyncUser(
            //                    $paymentMethodUpdated->getUser()
            //                        ->getId(), [
            //                        'custom_attributes' => [
            //                            'pianote_primary_payment_method_expiration_date' => $expirationDate,
            //                        ],
            //                    ]
            //                )
            //            );
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
        $userProduct = $userProductUpdated->getNewUserProduct();

        if ($userProduct->getProduct()
                ->getBrand() == 'pianote') {

            $this->pianoteIntercomSyncEventListener->handleUserProductUpdated($userProductUpdated);
        }
        elseif ($userProduct->getProduct()
                ->getBrand() == 'guitareo') {

            $this->guitareoIntercomSyncEventListener->handleUserProductUpdated($userProductUpdated);
        }
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        $userProduct = $userProductDeleted->getUserProduct();

        if ($userProduct->getProduct()
                ->getBrand() == 'pianote') {

            $this->pianoteIntercomSyncEventListener->handleUserProductDeleted($userProductDeleted);
        }
        elseif ($userProduct->getProduct()
                ->getBrand() == 'guitareo') {

            $this->guitareoIntercomSyncEventListener->handleUserProductDeleted($userProductDeleted);
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

        if (!empty($subscription->getProduct()) &&
            $subscription->getProduct()
                ->getBrand() == 'pianote') {

            $this->pianoteIntercomSyncEventListener->handleSubscriptionUpdated($subscriptionUpdated);
        }
        elseif (!empty($subscription->getOrder()) &&
            $subscription->getOrder()
                ->getBrand() == 'pianote') {

            $this->pianoteIntercomSyncEventListener->handleSubscriptionUpdated($subscriptionUpdated);
        }
        elseif (!empty($subscription->getProduct()) &&
            $subscription->getProduct()
                ->getBrand() == 'guitareo') {

            $this->guitareoIntercomSyncEventListener->handleSubscriptionUpdated($subscriptionUpdated);
        }
        elseif (!empty($subscription->getOrder()) &&
            $subscription->getOrder()
                ->getBrand() == 'guitareo') {

            $this->guitareoIntercomSyncEventListener->handleSubscriptionUpdated($subscriptionUpdated);
        }
    }
}