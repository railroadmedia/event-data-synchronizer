<?php

namespace Railroad\EventDataSynchronizer\Listeners\Intercom;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\PaymentMethod;
use Railroad\Ecommerce\Entities\Subscription;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserPaymentMethodsRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Intercomeo\Jobs\IntercomSyncUser;
use Railroad\Intercomeo\Services\IntercomeoService;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;

class IntercomSyncEventListener
{
    /**
     * @var IntercomeoService
     */
    protected $intercomeoService;

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var SubscriptionRepository
     */
    protected $subscriptionRepository;

    /**
     * @var UserProductRepository
     */
    protected $userProductRepository;

    /**
     * @var UserPaymentMethodsRepository
     */
    private $userPaymentMethodsRepository;

    protected const USER_ID_PREFIX = 'musora_';
    protected const BRANDS_TO_SYNC = ['pianote', 'guitareo'];

    public function __construct(
        IntercomeoService $intercomeoService,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository,
        UserPaymentMethodsRepository $userPaymentMethodsRepository
    )
    {
        $this->intercomeoService = $intercomeoService;
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->userPaymentMethodsRepository = $userPaymentMethodsRepository;
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
                new IntercomSyncUser(
                    self::USER_ID_PREFIX . $user->getId(), [
                        'email' => $user->getEmail(),
                        'created_at' => Carbon::parse($user->getCreatedAt(), 'UTC')->timestamp,
                        'name' => $user->getFirstName() .
                            (!empty($user->getLastName()) ? ' ' . $user->getLastName() : ''),
                        'avatar' => ['type' => 'avatar', 'image_url' => $user->getProfilePictureUrl()],
                        'custom_attributes' => [
                            'musora_profile_display_name' => $user->getDisplayName(),
                            'musora_profile_gender' => $user->getGender(),
                            'musora_profile_country' => $user->getCountry(),
                            'musora_profile_region' => $user->getRegion(),
                            'musora_profile_city' => $user->getCity(),
                            'musora_profile_birthday' => !empty($user->getBirthday()) ?
                                $user->getBirthday()->timestamp : null,
                            'musora_phone_number' => $user->getPhoneNumber(),
                            'musora_timezone' => $user->getTimezone(),
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
        if (!empty(
        $paymentMethodUpdated->getUser()
            ->getId()
        )) {
            $this->syncUserMembershipAndProductData(
                $paymentMethodUpdated->getUser()
                    ->getId()
            );
        }
    }

    /**
     * @param UserProductCreated $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $this->syncUserMembershipAndProductData(
            $userProductCreated->getUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $this->syncUserMembershipAndProductData(
            $userProductUpdated->getNewUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        $this->syncUserMembershipAndProductData(
            $userProductDeleted->getUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param SubscriptionCreated $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        $this->syncUserMembershipAndProductData(
            $subscriptionCreated->getSubscription()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param SubscriptionUpdated $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        $this->syncUserMembershipAndProductData(
            $subscriptionUpdated->getNewSubscription()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param $userId
     */
    public function syncUserMembershipAndProductData($userId)
    {
        $userSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($userId);
        $userProducts = $this->userProductRepository->getAllUsersProducts($userId);

        $finalCustomAttributes = [];

        foreach (self::BRANDS_TO_SYNC as $brand) {

            // user product related attributes
            $finalCustomAttributes = array_merge(
                $finalCustomAttributes,
                $this->getUserProductAttributes($userProducts, $brand)
            );

            $isLifetime = $finalCustomAttributes[$brand . '_membership_is_lifetime'] == true;

            // subscription related attributes
            $finalCustomAttributes = array_merge(
                $finalCustomAttributes,
                $this->getSubscriptionAttributes($userSubscriptions, $brand, $isLifetime)
            );

        }

        if (!empty($finalCustomAttributes)) {
            dispatch(
                new IntercomSyncUser(
                    self::USER_ID_PREFIX . $userId, [
                        'custom_attributes' => $finalCustomAttributes,
                    ]
                )
            );
        }
    }

    /**
     * @param Subscription[] $userSubscriptions
     * @param $brand
     * @param bool $isLifetime
     * @return array
     */
    private function getSubscriptionAttributes(array $userSubscriptions, $brand, $isLifetime = false)
    {
        $membershipRenewalDate = null;
        $membershipCancellationDate = null;
        $subscriptionStatus = null;
        $subscriptionStartedDate = null;
        $expirationDate = null;

        /**
         * @var $subscriptionToSync Subscription|null
         */

        // Subscriptions:
        // We only want to sync attributes to subscriptions for membership products.
        // If there are multiple membership subs we will always sync the one with the further paid_until
        // in the future.
        $subscriptionToSync = null;

        foreach ($userSubscriptions as $userSubscription) {
            // make sure this subscription is for the brand being processed
            if ($userSubscription->getBrand() !== $brand || empty($userSubscription->getProduct())) {
                continue;
            }

            // make sure the subscriptions product is in this brands pre-configured products that represent a membership
            if (!in_array(
                $userSubscription->getProduct()
                    ->getId(),
                config(
                    'event-data-synchronizer.' . $brand . '_membership_product_ids'
                )
            )) {
                continue;
            }

            if (empty($subscriptionToSync)) {
                $subscriptionToSync = $userSubscription;

                continue;
            }

            // if this subscription paid_until is further in the past than whatever is currently set, skip it
            // unless the set subscription is not-active and this one is, then use the active one
            if (!empty($subscriptionToSync) &&
                ($subscriptionToSync->getPaidUntil() < $userSubscription->getPaidUntil() ||
                    !$subscriptionToSync->getIsActive() &&
                    $userSubscription->getIsActive())) {
                $subscriptionToSync = $userSubscription;
            }
        }

        $expirationDate = null;

        if (!empty($subscriptionToSync)) {

            $totalPaymentsOnActiveSubscription = 0;

            foreach ($subscriptionToSync->getPayments() as $_payment) {
                if ($_payment->getTotalPaid() == $_payment->getTotalDue()) {
                    $totalPaymentsOnActiveSubscription++;
                }
            }

            $membershipRenewalDate = $subscriptionToSync->getPaidUntil()->timestamp;
            $membershipCancellationDate =
                !empty($subscriptionToSync->getCanceledOn()) ? $subscriptionToSync->getCanceledOn()->timestamp : null;
            $subscriptionStatus = $subscriptionToSync->getState();
            $subscriptionStartedDate = $subscriptionToSync->getCreatedAt()->timestamp;

            if (!empty($subscriptionToSync->getPaymentMethod())) {
                if ($subscriptionToSync->getPaymentMethod()
                        ->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD) {

                    $expirationDate = Carbon::parse(
                        $subscriptionToSync->getPaymentMethod()
                            ->getMethod()
                            ->getExpirationDate()
                    )->timestamp;
                }
                elseif ($subscriptionToSync->getPaymentMethod()
                        ->getMethodType() == PaymentMethod::TYPE_PAYPAL) {

                    $expirationDate = null;
                }
            }
        }

        $subscriptionProductTag = null;

        if (!empty($subscriptionToSync)) {
            $subscriptionProductTag =
                $subscriptionToSync->getIntervalCount() . '_' . $subscriptionToSync->getIntervalType();

        }

        // if the user is a lifetime make sure all subscription related info is set to null
        if ($isLifetime) {
            $membershipRenewalDate = null;
            $membershipCancellationDate = null;
            $subscriptionStatus = null;
            $subscriptionStartedDate = null;
            $subscriptionProductTag = null;
            $expirationDate = null;
        }

        return [
            $brand . '_membership_subscription_status' => $subscriptionStatus,
            $brand . '_membership_subscription_type' => $subscriptionProductTag,
            $brand . '_membership_subscription_renewal_date' => $membershipRenewalDate,
            $brand . '_membership_subscription_cancellation_date' => $membershipCancellationDate,
            $brand . '_membership_subscription_started_date' => $subscriptionStartedDate,
            $brand . '_primary_payment_method_expiration_date' => $expirationDate,
        ];
    }

    /**
     * @param UserProduct[] $userProducts
     * @param $brand
     * @return array
     */
    private function getUserProductAttributes(array $userProducts, $brand)
    {
        // sync membership expiration date
        $membershipUserProductToSync = null;

        foreach ($userProducts as $userProduct) {
            if ($userProduct->getProduct()
                    ->getBrand() !== $brand) {
                continue;
            }

            // make sure the subscriptions product is in this brands pre-configured products that represent a membership
            if (!in_array(
                $userProduct->getProduct()
                    ->getId(),
                config(
                    'event-data-synchronizer.' . $brand . '_membership_product_ids'
                )
            )) {
                continue;
            }

            if (empty($membershipUserProductToSync)) {
                $membershipUserProductToSync = $userProduct;

                continue;
            }

            // if its lifetime, use it
            if (!empty($membershipUserProductToSync) && empty($userProduct->getExpirationDate())) {
                $membershipUserProductToSync = $userProduct;

                break;
            }

            // if this subscription paid_until is further in the past than whatever is currently set, skip it
            // unless the set subscription is not-active and this one is, then use the active one
            if (!empty($membershipUserProductToSync) &&
                ($membershipUserProductToSync->getExpirationDate() < $userProduct->getExpirationDate())) {
                $membershipUserProductToSync = $userProduct;
            }
        }

        if (!empty($membershipUserProductToSync)) {
            $membershipAccessExpirationDate = $membershipUserProductToSync->getExpirationDate();
            $isLifetime = empty($membershipUserProductToSync->getExpirationDate());
        }
        else {
            $membershipAccessExpirationDate = null;
            $isLifetime = null;
        }

        $attributes = [
            $brand . '_membership_access_expiration_date' => !empty($membershipAccessExpirationDate) ?
                $membershipAccessExpirationDate->timestamp : null,
            $brand . '_membership_is_lifetime' => $isLifetime,
        ];

        // sync non-membership products
        foreach ($userProducts as $userProduct) {
            if ($userProduct->getProduct()
                    ->getBrand() !== $brand) {
                continue;
            }

            foreach (config('event-data-synchronizer.intercom_attribute_name_to_pack_product_ids') as $attributeName =>
                     $productIds) {
                if (!in_array(
                    $userProduct->getProduct()
                        ->getId(),
                    $productIds
                )) {
                    continue;
                }
                else {
                    $attributes[$attributeName] = true;
                }
            }
        }

        return $attributes;
    }
}