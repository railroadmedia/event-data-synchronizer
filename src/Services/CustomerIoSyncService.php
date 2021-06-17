<?php

namespace Railroad\EventDataSynchronizer\Services;

use Carbon\Carbon;
use Exception;
use Railroad\Ecommerce\Entities\PaymentMethod;
use Railroad\Ecommerce\Entities\Subscription;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Usora\Entities\User;

class CustomerIoSyncService
{
    /**
     * @var SubscriptionRepository
     */
    protected $subscriptionRepository;

    /**
     * @var UserProductRepository
     */
    protected $userProductRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @param  SubscriptionRepository  $subscriptionRepository
     * @param  UserProductRepository  $userProductRepository
     * @param  ProductRepository  $productRepository
     */
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository,
        ProductRepository $productRepository
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @param  User  $user
     * @param  array|null  $brands
     * @return array
     */
    public function getUsersCustomAttributes(User $user, array $brands = null)
    {
        $membershipAccessAttributes = $this->getUsersMembershipAccessAttributes($user, $brands);

        return array_merge(
            $this->getUsersMusoraProfileAttributes($user),
            $membershipAccessAttributes,
            $this->getUsersSubscriptionAttributes($user, $membershipAccessAttributes, $brands),
            $this->getUsersProductOwnershipStrings($user, $brands)
        );
    }

    /**
     * @param  User  $user
     * @return array
     */
    public function getUsersMusoraProfileAttributes(User $user)
    {
        return [
            'musora_profile_display-name' => $user->getDisplayName(),
            'musora_profile_gender' => $user->getGender(),
            'musora_profile_country' => $user->getCountry(),
            'musora_profile_region' => $user->getRegion(),
            'musora_profile_city' => $user->getCity(),
            'musora_profile_birthday' => !empty($user->getBirthday()) ? $user->getBirthday()->timestamp : null,
            'musora_phone-number' => $user->getPhoneNumber(),
            'musora_timezone' => $user->getTimezone(),
            'musora_notify_of_weekly_updates' => $user->getNotifyWeeklyUpdate(),
        ];
    }

    /**
     * Attribute list:
     * rumeo_membership_access-expiration-date (null if BRAND_membership_is_lifetime is true)
     * BRAND_membership_is_lifetime
     * BRAND_membership_latest-start-date
     * BRAND_membership_first-start-date
     *
     * @param  User  $user
     * @param  array  $brands
     * @return array
     */
    public function getUsersMembershipAccessAttributes(User $user, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.customer_io_brands_to_sync');
        }

        $userProducts = $this->userProductRepository->getAllUsersProducts($user->getId());

        $productAttributes = [];

        foreach ($brands as $brand) {
            // get all the eligible user products for this brand
            $eligibleUserProducts = [];

            foreach ($userProducts as $userProductIndex => $userProduct) {
                if ($userProduct->getProduct()->getBrand() !== $brand) {
                    continue;
                }

                // make sure the subscriptions product is in this brands pre-configured products that represent a membership
                if (!in_array(
                    $userProduct->getProduct()->getSku(),
                    config('event-data-synchronizer.'.$brand.'_membership_product_skus', [])
                )) {
                    continue;
                }

                $eligibleUserProducts[] = $userProduct;
            }

            // get attributes related to the latest user membership product
            $latestMembershipUserProductToSync = null;

            foreach ($eligibleUserProducts as $eligibleUserProductIndex => $eligibleUserProduct) {
                if (empty($latestMembershipUserProductToSync)) {
                    $latestMembershipUserProductToSync = $eligibleUserProduct;

                    continue;
                }

                // if its lifetime, use it
                if (!empty($latestMembershipUserProductToSync) && empty($eligibleUserProduct->getExpirationDate())) {
                    $latestMembershipUserProductToSync = $eligibleUserProduct;

                    break;
                }

                // if this product expiration date is further in the past than whatever is currently set, skip it
                if (!empty($latestMembershipUserProductToSync) &&
                    ($latestMembershipUserProductToSync->getExpirationDate() < $eligibleUserProduct->getExpirationDate(
                        ))) {
                    $latestMembershipUserProductToSync = $eligibleUserProduct;
                }
            }

            // get attributes related to the first created user membership product
            $firstMembershipUserProductToSync = null;

            foreach ($eligibleUserProducts as $eligibleUserProductIndex => $eligibleUserProduct) {
                if (empty($firstMembershipUserProductToSync)) {
                    $firstMembershipUserProductToSync = $eligibleUserProduct;

                    continue;
                }

                // if this product expiration date is further in the past than whatever is currently set, skip it
                if (!empty($firstMembershipUserProductToSync) &&
                    ($eligibleUserProduct->getCreatedAt() < $firstMembershipUserProductToSync->getCreatedAt())) {
                    $firstMembershipUserProductToSync = $eligibleUserProduct;
                }
            }

            if (!empty($latestMembershipUserProductToSync) && !empty($firstMembershipUserProductToSync)) {
                $membershipAccessExpirationDate = $latestMembershipUserProductToSync->getExpirationDate();
                $membershipLatestAccessStartDate = $latestMembershipUserProductToSync->getCreatedAt();
                $membershipFirstAccessStartDate = $firstMembershipUserProductToSync->getCreatedAt();
            } else {
                $membershipAccessExpirationDate = null;
                $membershipLatestAccessStartDate = null;
                $membershipFirstAccessStartDate = null;
            }

            $productAttributes += [
                $brand.'_membership_access-expiration-date' => !empty($membershipAccessExpirationDate) ?
                    $membershipAccessExpirationDate->timestamp : null,
                $brand.'_membership_latest-access-start-date' => !empty($membershipLatestAccessStartDate) ?
                    $membershipLatestAccessStartDate->timestamp : null,
                $brand.'_membership_first-access-start-date' => !empty($membershipFirstAccessStartDate) ?
                    $membershipFirstAccessStartDate->timestamp : null,
                $brand.'_membership_is_lifetime' => !empty($latestMembershipUserProductToSync) ?
                    empty($latestMembershipUserProductToSync->getExpirationDate()) : null,
            ];
        }

        return $productAttributes;
    }

    /**
     * If no brands are passed in this will get attributes for all brands in the config.
     * We need the $userProductAttributes since if the user is lifetime all the attributes should be null.
     *
     * @param  User  $user
     * @param  array  $userMembershipAccessAttributes
     * @param  array  $brands
     * @return array
     */
    public function getUsersSubscriptionAttributes(User $user, array $userMembershipAccessAttributes, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.customer_io_brands_to_sync');
        }

        $subscriptionAttributes = [];

        $userSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($user->getId());

        foreach ($brands as $brand) {
            $membershipRenewalDate = null;
            $membershipCancellationDate = null;
            $membershipCancellationReason = null;
            $membershipRenewalAttempts = 0;
            $subscriptionStatus = null;
            $subscriptionPriceCents = null;
            $subscriptionCurrency = null;
            $latestSubscriptionStartedDate = null;
            $firstSubscriptionStartedDate = null;
            $trialType = null;
            $expirationDate = null;
            $isAppSignup = false;

            /**
             * @var $latestSubscriptionToSync Subscription|null
             */
            $latestSubscriptionToSync = null;

            /**
             * @var $latestSubscriptionToSync Subscription|null
             */
            $firstSubscriptionToSync = null;

            // We only want to sync attributes to subscriptions for membership products.
            // If there are multiple active membership subs we will always sync the one with the further paid_until
            // in the future.
            foreach ($userSubscriptions as $userSubscription) {
                // make sure this subscription is for a single membership product and its the right brand
                if (empty($userSubscription->getProduct()) ||
                    $userSubscription->getProduct()
                        ->getBrand() != $brand) {
                    continue;
                }

                // make sure the subscriptions product is in this brands pre-configured products that represent a membership
                if (!in_array(
                    $userSubscription->getProduct()
                        ->getSku(),
                    config(
                        'event-data-synchronizer.'.
                        $userSubscription->getProduct()
                            ->getBrand().
                        '_membership_product_skus',
                        []
                    )
                )) {
                    continue;
                }

                if (empty($latestSubscriptionToSync)) {
                    $latestSubscriptionToSync = $userSubscription;

                    continue;
                }

                // if this subscription paid_until is further in the past than whatever is currently set, skip it
                // unless the set subscription is not-active and this one is, then use the active one
                if (!empty($latestSubscriptionToSync) &&
                    ($latestSubscriptionToSync->getPaidUntil() < $userSubscription->getPaidUntil() ||
                        !$latestSubscriptionToSync->getIsActive() &&
                        $userSubscription->getIsActive())) {
                    $latestSubscriptionToSync = $userSubscription;
                }
            }

            foreach ($userSubscriptions as $userSubscription) {
                // make sure this subscription is for a single membership product and its the right brand
                if (empty($userSubscription->getProduct()) ||
                    $userSubscription->getProduct()
                        ->getBrand() != $brand) {
                    continue;
                }

                // make sure the subscriptions product is in this brands pre-configured products that represent a membership
                if (!in_array(
                    $userSubscription->getProduct()
                        ->getSku(),
                    config(
                        'event-data-synchronizer.'.
                        $userSubscription->getProduct()
                            ->getBrand().
                        '_membership_product_skus',
                        []
                    )
                )) {
                    continue;
                }

                if (empty($firstSubscriptionToSync)) {
                    $firstSubscriptionToSync = $userSubscription;

                    continue;
                }

                // get the earliest started subscription
                if (!empty($firstSubscriptionToSync) &&
                    $firstSubscriptionToSync->getStartDate() > $userSubscription->getStartDate()) {
                    $firstSubscriptionToSync = $userSubscription;
                }
            }

            if (!empty($firstSubscriptionToSync)) {
                $firstSubscriptionStartedDate = $firstSubscriptionToSync->getStartDate()->timestamp;
            }

            // Get expiration date
            if (!empty($latestSubscriptionToSync)) {
                $totalPaymentsOnActiveSubscription = 0;

                foreach ($latestSubscriptionToSync->getPayments() as $_payment) {
                    if ($_payment->getTotalPaid() == $_payment->getTotalDue()) {
                        $totalPaymentsOnActiveSubscription++;
                    }
                }

                $membershipRenewalDate = $latestSubscriptionToSync->getPaidUntil()->timestamp;
                $subscriptionPriceCents = $latestSubscriptionToSync->getTotalPrice() * 100;
                $subscriptionCurrency = $latestSubscriptionToSync->getCurrency();
                $membershipRenewalAttempts = $latestSubscriptionToSync->getRenewalAttempt();
                $membershipCancellationDate =
                    !empty($latestSubscriptionToSync->getCanceledOn()) ?
                        $latestSubscriptionToSync->getCanceledOn()->timestamp :
                        null;
                $membershipCancellationReason = $latestSubscriptionToSync->getCancellationReason();
                $subscriptionStatus = $latestSubscriptionToSync->getState();
                $latestSubscriptionStartedDate = $latestSubscriptionToSync->getCreatedAt()->timestamp;

                if (in_array(
                    $latestSubscriptionToSync->getType(),
                    [
                        Subscription::TYPE_APPLE_SUBSCRIPTION,
                        Subscription::TYPE_GOOGLE_SUBSCRIPTION,
                    ]
                )) {
                    $isAppSignup = true;
                }
                // i could not figure out how else to catch the doctrine exception when no payment method exists - caleb sept 2019
                try {
                    if (!empty($latestSubscriptionToSync->getPaymentMethod())) {
                        if ($latestSubscriptionToSync->getPaymentMethod()
                                ->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD) {
                            $expirationDate = Carbon::parse(
                                $latestSubscriptionToSync->getPaymentMethod()
                                    ->getMethod()
                                    ->getExpirationDate()
                            )->timestamp;
                        } elseif ($latestSubscriptionToSync->getPaymentMethod()
                                ->getMethodType() == PaymentMethod::TYPE_PAYPAL) {
                            $expirationDate = null;
                        }
                    }
                } catch (Exception $exception) {
                    $expirationDate = null;
                }
            }

            // if the cancelled_on date is changed to null, then set the cancellation_reason to null as well
            if (!empty($latestSubscriptionToSync)) {
                $cancelledOnIsNull = is_null($latestSubscriptionToSync->getCanceledOn());
                if ($cancelledOnIsNull) {
                    $latestSubscriptionToSync->setCancellationReason(null);
                }
            }

            $subscriptionProductTag = null;

            if (!empty($latestSubscriptionToSync)) {
                $subscriptionProductTag =
                    $latestSubscriptionToSync->getIntervalCount().'_'.$latestSubscriptionToSync->getIntervalType();

                // trial type
                $customerIoTrialProductSkuToType =
                    config('event-data-synchronizer.customer_io_trial_product_sku_to_type', []);

                if (!empty($customerIoTrialProductSkuToType[$latestSubscriptionToSync->getProduct()->getBrand()]) &&
                    !empty(
                    $customerIoTrialProductSkuToType[$latestSubscriptionToSync->getProduct()->getBrand(
                    )][$latestSubscriptionToSync->getProduct()->getSku()]
                    )) {
                    $trialType = $customerIoTrialProductSkuToType[$latestSubscriptionToSync->getProduct()->getBrand()]
                        [$latestSubscriptionToSync->getProduct()->getSku()] ?? null;
                }
            }

            // if the user is a lifetime make sure all subscription related info is set to null
            if (($userMembershipAccessAttributes[$brand.'_membership_is_lifetime'] ?? false) == true) {
                $subscriptionPriceCents = null;
                $subscriptionCurrency = null;
                $membershipRenewalDate = null;
                $membershipRenewalAttempts = null;
                $membershipCancellationDate = null;
                $membershipCancellationReason = null;
                $subscriptionStatus = null;
                $latestSubscriptionStartedDate = null;
                $firstSubscriptionStartedDate = null;
                $subscriptionProductTag = null;
                $trialType = null;
                $expirationDate = null;
                $isAppSignup = null;
            }


            $subscriptionAttributes += [
                $brand.'_membership_status' => $subscriptionStatus,
                $brand.'_membership_subscription_type' => $subscriptionProductTag,
                $brand.'_membership_subscription-rate-cents' => $subscriptionPriceCents,
                $brand.'_membership_subscription-currency' => $subscriptionCurrency,
                $brand.'_membership_subscription_renewal-date' => $membershipRenewalDate,
                $brand.'_retention_failed-billing_membership_subscription-renewal-attempts' => $membershipRenewalAttempts,
                $brand.'_membership_subscription_cancellation-date' => $membershipCancellationDate,
                $brand.'_membership_subscription_cancellation-reason' => $membershipCancellationReason,
                $brand.'_membership_subscription_latest-start-date' => $latestSubscriptionStartedDate,
                $brand.'_membership_subscription_first-start-date' => $firstSubscriptionStartedDate,
                $brand.'_membership_subscription_trial-type' => $trialType,
                $brand.'_user_payment_primary-method-expiration-date' => $expirationDate,
                $brand.'_membership_subscription_source_app-store' => $isAppSignup,
            ];

            if ($isAppSignup) {
                $subscriptionAttributes[$brand.'_membership_trial_type'] = '7_days_free';
            }

            // if the subscription due date is passed by more than a day, or its cancelled or suspended, but the user
            // still has access to the membership, they are considered in 'escrow'
            // todo: build, it should change the _membership_status attribute if they are in escrow
        }

        return $subscriptionAttributes;
    }

    /**
     * This returns an array of 2 strings:
     * ['BRAND_owned_pack_product_skus' => 'string', 'BRAND_owned_pack_product_ids' => 'string']
     *
     * The first string is a list of all the users owned pack skus separated by ', '
     * The first string is a list of all the users owned pack ids separated by ', '
     * each entry is also surrounded by underscores
     *
     * Ex: _electrify-your-drumming_, _rock-drumming-masterclass-pack_, _LDS-DIGI_
     * Ex: _523_, _9_, _3774_
     *
     * @param  User  $user
     * @param  array  $brands
     * @return array
     */
    public function getUsersProductOwnershipStrings(User $user, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.customer_io_brands_to_sync');
        }

        $packSkusToSync = config('event-data-synchronizer.customer_io_pack_skus_to_sync_ownership');

        /**
         * @var $userProducts UserProduct[]
         */
        $userProducts = $this->userProductRepository->getAllUsersProducts($user->getId());

        $finalArray = [];

        foreach ($brands as $brand) {
            $productSkuArray = [];
            $idArray = [];

            foreach ($userProducts as $userProduct) {
                if ($userProduct->getProduct()->getBrand() !== $brand) {
                    continue;
                }

                if (in_array($userProduct->getProduct()->getSku(), $packSkusToSync) && $userProduct->isValid()) {
                    $productSkuArray[] = "_".$userProduct->getProduct()->getSku()."_";
                    $idArray[] = "_".$userProduct->getProduct()->getId()."_";
                }
            }

            if (!empty($productSkuArray)) {
                $finalArray[$brand.'_owned_pack_product_skus'] = implode(', ', $productSkuArray);
            }

            if (!empty($idArray)) {
                $finalArray[$brand.'_owned_pack_product_ids'] = implode(', ', $idArray);
            }
        }

        return $finalArray;
    }
}