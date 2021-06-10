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
use Railroad\EventDataSynchronizer\ValueObjects\IntercomAddRemoveTagsVO;
use Railroad\Intercomeo\Jobs\IntercomSyncUser;
use Railroad\Intercomeo\Jobs\IntercomTagUsers;
use Railroad\Intercomeo\Jobs\IntercomUnTagUsers;
use Railroad\Intercomeo\Services\IntercomeoService;
use Railroad\Usora\Entities\User;
use Throwable;

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
     * @var IntercomeoService
     */
    protected $intercomeoService;

    /**
     * @var string
     */
    public static $userIdPrefix;

    /**
     * @param  SubscriptionRepository  $subscriptionRepository
     * @param  UserProductRepository  $userProductRepository
     * @param  ProductRepository  $productRepository
     * @param  IntercomeoService  $intercomeoService
     */
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository,
        ProductRepository $productRepository,
        IntercomeoService $intercomeoService
    ) {
        self::$userIdPrefix = config('event-data-synchronizer.intercom_user_id_prefix', 'musora_');

        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
        $this->intercomeoService = $intercomeoService;
    }

    /**
     * @param  User  $user
     * @return array
     */
    public function getUsersCustomeAttributes(User $user)
    {
        return array_merge(
            $this->getUsersMusoraProfileAttributes($user),
            $this->getUsersMembershipAccessAttributes($user)
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
                $brand.'_membership_latest-start-date' => !empty($membershipLatestAccessStartDate) ?
                    $membershipLatestAccessStartDate->timestamp : null,
                $brand.'_membership_first-start-date' => !empty($membershipFirstAccessStartDate) ?
                    $membershipFirstAccessStartDate->timestamp : null,
                $brand.'_membership_is_lifetime' => !empty($latestMembershipUserProductToSync) ?
                    empty($latestMembershipUserProductToSync->getExpirationDate()) : null,
            ];
        }

        return $productAttributes;
    }

    // ---------------

    /**
     * If no brands are passed in this will get attributes for all brands in the config.
     *
     * @param  User  $user
     * @param  array  $brands
     */
    public function syncUsersAttributes(User $user, $brands = [])
    {
        dispatch(
            new IntercomSyncUser(
                self::$userIdPrefix.$user->getId(), array_merge(
                                                      $this->getUsersBuiltInAttributes($user),
                                                      [
                                                          'custom_attributes' => $this->getUsersCustomAttributes(
                                                              $user,
                                                              $brands
                                                          ),
                                                      ]
                                                  )
            )
        );
    }

    /**
     * Because the intercom API is dump, we can only apply 1 tag to a user per request. In order to use the
     * minimum amount of requests possible, we must pull the intercom user first and see which tags are already applied.
     * We can use this info to only add or remove the tags that are necessary.
     *
     * When tagging in bulk we should not use this function rather use the intercomeo tag multiple users with 1 tag
     * functionality.
     *
     * @param  User  $user
     * @param  array  $brands
     */
    public function syncUsersProductOwnershipTags(User $user, $brands = [])
    {
        $productOwnershipTagsVO = $this->getUsersProductOwnershipTags($user, $brands);

        $intercomUser = null;

        try {
            $intercomUser = $this->intercomeoService->getUser(self::$userIdPrefix.$user->getId());
        } catch (Throwable $throwable) {
        }

        $existingIntercomUserTagNames = [];

        if (!empty($intercomUser) && !empty($intercomUser->tags->tags)) {
            foreach ($intercomUser->tags->tags as $tagObject) {
                $existingIntercomUserTagNames[] = $tagObject->name;
            }
        }

        $productOwnershipTagsVO->tagsToAdd =
            array_diff($productOwnershipTagsVO->tagsToAdd, $existingIntercomUserTagNames);

        $productOwnershipTagsVO->tagsToRemove =
            array_intersect($existingIntercomUserTagNames, $productOwnershipTagsVO->tagsToRemove);

        // add tags, only add ones that don't already exist on the intercom user
        foreach ($productOwnershipTagsVO->tagsToAdd as $tagToAdd) {
            dispatch(
                new IntercomTagUsers(
                    [self::$userIdPrefix.$user->getId()], $tagToAdd
                )
            );
        }

        // remove tags, only add ones that do already exist on the intercom user
        foreach ($productOwnershipTagsVO->tagsToRemove as $tagsToRemove) {
            dispatch(
                new IntercomUnTagUsers(
                    [self::$userIdPrefix.$user->getId()], $tagsToRemove
                )
            );
        }
    }

    /**
     * @param  User  $user
     * @param  array  $brands
     * @return IntercomAddRemoveTagsVO
     */
    public function getUsersProductOwnershipTags(User $user, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.intercom_brands_to_sync');
        }

        $intercomTagNamesToProductSkus = config('event-data-synchronizer.intercom_tag_name_to_product_skus');

        /**
         * @var $userProducts UserProduct[]
         */
        $userProducts = $this->userProductRepository->getAllUsersProducts($user->getId());

        $allProductsKeyedBySku = key_array_of_entities_by(
            $this->productRepository->bySkus(array_values($intercomTagNamesToProductSkus)),
            'getSku'
        );

        $tagsToAdd = [];
        $tagsToRemove = [];

        foreach ($brands as $brand) {
            // tags to add
            foreach ($userProducts as $userProduct) {
                if ($userProduct->getProduct()
                        ->getBrand() !== $brand) {
                    continue;
                }

                foreach ($intercomTagNamesToProductSkus as $tagName => $productSkus) {
                    if (in_array(
                            $userProduct->getProduct()
                                ->getSku(),
                            $productSkus
                        ) && $userProduct->isValid()) {
                        $tagsToAdd[] = $tagName;
                    }
                }
            }

            // tags to remove
            // we only want to remove tags for the brands passed in to the func
            foreach ($intercomTagNamesToProductSkus as $tagName => $productSkus) {
                foreach ($productSkus as $productSku) {
                    $product = $allProductsKeyedBySku[$productSku] ?? null;

                    if (empty($product) || $product->getBrand() !== $brand) {
                        continue;
                    }

                    if (!in_array($tagName, $tagsToAdd)) {
                        $tagsToRemove[] = $tagName;
                    }
                }
            }
        }

        return new IntercomAddRemoveTagsVO(array_unique($tagsToAdd), array_unique($tagsToRemove));
    }

    /**
     * @param  User  $user
     * @return array
     */
    public function getUsersBuiltInAttributes(User $user)
    {
        return [
            'email' => $user->getEmail(),
            'created_at' => Carbon::parse($user->getCreatedAt(), 'UTC')->timestamp,
            'name' => $user->getFirstName().(!empty($user->getLastName()) ? ' '.$user->getLastName() : ''),
            'avatar' => ['type' => 'avatar', 'image_url' => $user->getProfilePictureUrl()],
        ];
    }

    /**
     * @param  User  $user
     * @param  array  $brands
     * @return array
     */
    public function getUsersCustomAttributes(User $user, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.intercom_brands_to_sync');
        }

        $userProfileAttributes = $this->getUsersCustomProfileAttributes($user);

        $userProductAttributes = $this->getUsersMembershipAttributes($user, $brands);

        $userSubscriptionAttributes = $this->getUsersSubscriptionAttributes($user, $userProductAttributes, $brands);

        return $userProfileAttributes + $userProductAttributes + $userSubscriptionAttributes;
    }

    /**
     * @param  User  $user
     * @return array
     */
    public function getUsersCustomProfileAttributes(User $user)
    {
        return [
            'musora_profile_display_name' => $user->getDisplayName(),
            'musora_profile_gender' => $user->getGender(),
            'musora_profile_country' => $user->getCountry(),
            'musora_profile_region' => $user->getRegion(),
            'musora_profile_city' => $user->getCity(),
            'musora_profile_birthday' => !empty($user->getBirthday()) ? $user->getBirthday()->timestamp : null,
            'musora_phone_number' => $user->getPhoneNumber(),
            'musora_timezone' => $user->getTimezone(),
            'musora_notify_of_weekly_updates' => $user->getNotifyWeeklyUpdate(),
        ];
    }

    /**
     * If no brands are passed in this will get attributes for all brands in the config.
     * We need the $userProductAttributes since if the user is lifetime all the attributes should be null.
     *
     * @param  User  $user
     * @param  array  $userProductAttributes
     * @param  array  $brands
     * @return array
     */
    public function getUsersSubscriptionAttributes(User $user, array $userProductAttributes, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.intercom_brands_to_sync');
        }

        $subscriptionAttributes = [];

        $userSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($user->getId());

        foreach ($brands as $brand) {
            $membershipRenewalDate = null;
            $membershipCancellationDate = null;
            $membershipCancellationReason = null;
            $membershipRenewalAttempts = 0;
            $subscriptionStatus = null;
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
                $intercomTrialProductSkuToType =
                    config('event-data-synchronizer.intercom_trial_product_sku_to_type', []);

                if (!empty($intercomTrialProductSkuToType[$latestSubscriptionToSync->getProduct()->getBrand()]) &&
                    !empty(
                    $intercomTrialProductSkuToType[$latestSubscriptionToSync->getProduct()->getBrand(
                    )][$latestSubscriptionToSync->getProduct()->getSku()]
                    )) {
                    $trialType = $intercomTrialProductSkuToType[$latestSubscriptionToSync->getProduct()->getBrand()]
                        [$latestSubscriptionToSync->getProduct()->getSku()] ?? null;
                }
            }

            // if the user is a lifetime make sure all subscription related info is set to null
            if (($userProductAttributes[$brand.'_membership_is_lifetime'] ?? false) == true) {
                $membershipRenewalDate = null;
                $membershipRenewalAttempts = null;
                $membershipCancellationDate = null;
                $membershipCancellationReason = null;
                $subscriptionStatus = null;
                $latestSubscriptionStartedDate = null;
                $subscriptionProductTag = null;
                $trialType = null;
                $expirationDate = null;
            }


            $subscriptionAttributes += [
                $brand.'_membership_status' => $subscriptionStatus,
                $brand.'_membership_type' => $subscriptionProductTag,
                $brand.'_membership_renewal_date' => $membershipRenewalDate,
                $brand.'_membership_renewal_attempt' => $membershipRenewalAttempts,
                $brand.'_membership_cancellation_date' => $membershipCancellationDate,
                $brand.'_membership_cancellation_reason' => $membershipCancellationReason,
                $brand.'_membership_latest_start_date' => $latestSubscriptionStartedDate,
                $brand.'_membership_first_start_date' => $firstSubscriptionStartedDate,
                $brand.'_membership_trial_type' => $trialType,
                $brand.'_primary_payment_method_expiration_date' => $expirationDate,
                $brand.'_app_membership' => $isAppSignup,
            ];

            if ($isAppSignup) {
                $subscriptionAttributes[$brand.'_membership_trial_type'] = '7_days_free';
            }
        }

        return $subscriptionAttributes;
    }

}