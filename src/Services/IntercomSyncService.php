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

class IntercomSyncService extends IntercomSyncServiceBase
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
        parent::__construct();

        self::$userIdPrefix = config('event-data-synchronizer.intercom_user_id_prefix', 'musora_');

        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
        $this->intercomeoService = $intercomeoService;
    }

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
                self::$userIdPrefix . $user->getId(), array_merge(
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
            $intercomUser = $this->intercomeoService->getUser(self::$userIdPrefix . $user->getId());
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
                    [self::$userIdPrefix . $user->getId()], $tagToAdd
                )
            );
        }

        // remove tags, only add ones that do already exist on the intercom user
        foreach ($productOwnershipTagsVO->tagsToRemove as $tagsToRemove) {
            dispatch(
                new IntercomUnTagUsers(
                    [self::$userIdPrefix . $user->getId()], $tagsToRemove
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
            'name' => $user->getFirstName() . (!empty($user->getLastName()) ? ' ' . $user->getLastName() : ''),
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
        ];
    }

    /**
     * @param  User  $user
     * @param  array  $brands
     * @return array
     */
    public function getUsersMembershipAttributes(User $user, $brands = [])
    {
        if (empty($brands)) {
            $brands = config('event-data-synchronizer.intercom_brands_to_sync');
        }

        $userProducts = $this->userProductRepository->getAllUsersProducts($user->getId());

        $productAttributes = [];

        foreach ($brands as $brand) {
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
                        ->getSku(),
                    config(
                        'event-data-synchronizer.' . $brand . '_membership_product_skus',
                        []
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
            } else {
                $membershipAccessExpirationDate = null;
                $isLifetime = null;
            }

            $productAttributes += [
                $brand . '_membership_access_expiration_date' => !empty($membershipAccessExpirationDate) ?
                    $membershipAccessExpirationDate->timestamp : null,
                $brand . '_membership_is_lifetime' => $isLifetime,
            ];
        }

        return $productAttributes;
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
            $subscriptionStatus = null;
            $subscriptionStartedDate = null;
            $expirationDate = null;
            $isAppSignup = false;

            /**
             * @var $subscriptionToSync Subscription|null
             */
            $subscriptionToSync = null;

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
                        'event-data-synchronizer.' .
                        $userSubscription->getProduct()
                            ->getBrand() .
                        '_membership_product_skus',
                        []
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

            // Get expiration date
            if (!empty($subscriptionToSync)) {
                $totalPaymentsOnActiveSubscription = 0;

                foreach ($subscriptionToSync->getPayments() as $_payment) {
                    if ($_payment->getTotalPaid() == $_payment->getTotalDue()) {
                        $totalPaymentsOnActiveSubscription++;
                    }
                }

                $membershipRenewalDate = $subscriptionToSync->getPaidUntil()->timestamp;
                $membershipCancellationDate =
                    !empty($subscriptionToSync->getCanceledOn()) ? $subscriptionToSync->getCanceledOn()->timestamp :
                        null;
                $subscriptionStatus = $subscriptionToSync->getState();
                $subscriptionStartedDate = $subscriptionToSync->getCreatedAt()->timestamp;

                if(in_array($subscriptionToSync->getType(), [
                    Subscription::TYPE_APPLE_SUBSCRIPTION,
                    Subscription::TYPE_GOOGLE_SUBSCRIPTION
                ])) {
                    $isAppSignup = true;
                }
                // i could not figure out how else to catch the doctrine exception when no payment method exists - caleb sept 2019
                try {
                    if (!empty($subscriptionToSync->getPaymentMethod())) {
                        if ($subscriptionToSync->getPaymentMethod()
                                ->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD) {
                            $expirationDate = Carbon::parse(
                                $subscriptionToSync->getPaymentMethod()
                                    ->getMethod()
                                    ->getExpirationDate()
                            )->timestamp;
                        } elseif ($subscriptionToSync->getPaymentMethod()
                                ->getMethodType() == PaymentMethod::TYPE_PAYPAL) {
                            $expirationDate = null;
                        }
                    }
                } catch (Exception $exception) {
                    $expirationDate = null;
                }
            }

            $subscriptionProductTag = null;

            if (!empty($subscriptionToSync)) {
                $subscriptionProductTag =
                    $subscriptionToSync->getIntervalCount() . '_' . $subscriptionToSync->getIntervalType();
            }

            // if the user is a lifetime make sure all subscription related info is set to null
            if (($userProductAttributes[$brand . '_membership_is_lifetime'] ?? false) == true) {
                $membershipRenewalDate = null;
                $membershipCancellationDate = null;
                $subscriptionStatus = null;
                $subscriptionStartedDate = null;
                $subscriptionProductTag = null;
                $expirationDate = null;
            }

            $subscriptionAttributes += [
                $brand . '_membership_status' => $subscriptionStatus,
                $brand . '_membership_type' => $subscriptionProductTag,
                $brand . '_membership_renewal_date' => $membershipRenewalDate,
                $brand . '_membership_cancellation_date' => $membershipCancellationDate,
                $brand . '_membership_started_date' => $subscriptionStartedDate,
                $brand . '_primary_payment_method_expiration_date' => $expirationDate,
                $brand . '_app_membership' => $isAppSignup,
            ];
        }

        return $subscriptionAttributes;
    }

}