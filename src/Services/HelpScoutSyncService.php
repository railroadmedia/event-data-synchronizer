<?php

namespace Railroad\EventDataSynchronizer\Services;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\Subscription;
use Railroad\Ecommerce\Repositories\OrderRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Usora\Entities\User;

class HelpScoutSyncService
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var SubscriptionRepository
     */
    protected $subscriptionRepository;

    /**
     * @var UserProductRepository
     */
    protected $userProductRepository;

    /**
     * @param  OrderRepository $orderRepository
     * @param  SubscriptionRepository  $subscriptionRepository
     * @param  UserProductRepository  $userProductRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
    }

    public function getUsersAttributes(User $user): array
    {
        return array_merge(
            $this->getUsersMusoraProfileAttributes($user),
            $this->getUsersMembershipAttributes($user)
        );
    }

    /**
     * @param  User  $user
     *
     * @return array
     */
    public function getUsersMusoraProfileAttributes(User $user): array
    {
        return [
            'musora_profile_preferred-name' => !empty($user->getFirstName()) ? $user->getFirstName() : $user->getDisplayName(),
            'musora_profile_country' => $user->getCountry(),
            'musora_profile_city' => $user->getCity(),
            'musora_profile_phone-number' => $user->getPhoneNumber(),
            'musora_profile_timezone' => $user->getTimezone(),
        ];
    }

    /**
     * @param  User  $user
     *
     * @return array
     */
    public function getUsersMembershipAttributes(User $user): array
    {
        $brands = config('event-data-synchronizer.help_scout_sync_brands', []);

        $userProducts = $this->userProductRepository->getAllUsersProducts($user->getId());

        $userSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($user->getId());

        $attributes = [];

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
            $latestMembershipUserProduct = null;

            foreach ($eligibleUserProducts as $eligibleUserProductIndex => $eligibleUserProduct) {
                if (empty($latestMembershipUserProduct)) {
                    $latestMembershipUserProduct = $eligibleUserProduct;

                    continue;
                }

                // if its lifetime, use it
                if (!empty($latestMembershipUserProduct) && empty($eligibleUserProduct->getExpirationDate())) {
                    $latestMembershipUserProduct = $eligibleUserProduct;

                    break;
                }

                // if this product expiration date is further in the past than whatever is currently set, skip it
                if (!empty($latestMembershipUserProduct) &&
                    ($latestMembershipUserProduct->getExpirationDate() < $eligibleUserProduct->getExpirationDate(
                        ))) {
                    $latestMembershipUserProduct = $eligibleUserProduct;
                }
            }

            // get attributes related to the first created user membership product
            $firstMembershipUserProduct = null;

            foreach ($eligibleUserProducts as $eligibleUserProductIndex => $eligibleUserProduct) {
                if (empty($firstMembershipUserProduct)) {
                    $firstMembershipUserProduct = $eligibleUserProduct;

                    continue;
                }

                // if this product expiration date is further in the past than whatever is currently set, skip it
                if (!empty($firstMembershipUserProduct) &&
                    ($eligibleUserProduct->getCreatedAt() < $firstMembershipUserProduct->getCreatedAt())) {
                    $firstMembershipUserProduct = $eligibleUserProduct;
                }
            }

            $membershipDetails = null;
            $membershipRenewalDate = null;
            $membershipLatestAccessStartDate = null;
            $membershipFirstAccessStartDate = null;
            $membershipCancellationDate = null;
            $membershipCancellationReason = null;
            $membershipFailedRenewalAttempts = null;
            $membershipSourceAppStore = null;

            if (!empty($latestMembershipUserProduct) && !empty($firstMembershipUserProduct)) {
                $membershipRenewalDate = $latestMembershipUserProduct->getExpirationDate();
                $membershipLatestAccessStartDate = $latestMembershipUserProduct->getCreatedAt();
                $membershipFirstAccessStartDate = $firstMembershipUserProduct->getCreatedAt();

                // find the latest subscription with product sku matching the latestMembershipUserProduct product sku

                $latestSubscription = null;

                foreach ($userSubscriptions as $userSubscription) {
                    if ($userSubscription->getProduct()->getSku() == $latestMembershipUserProduct->getProduct()->getSku()) {
                        if (empty($latestSubscription)) {
                            $latestSubscription = $userSubscription;

                            continue;
                        }

                        // if this subscription paid_until is further in the past than whatever is currently set, skip it
                        // unless the set subscription is not-active and this one is, then use the active one
                        if (!empty($latestSubscription) &&
                            ($latestSubscription->getPaidUntil() < $userSubscription->getPaidUntil() ||
                                !$latestSubscription->getIsActive() &&
                                $userSubscription->getIsActive())) {
                            $latestSubscription = $userSubscription;
                        }
                    }
                }

                if (!empty($latestSubscription)) {
                    $membershipRenewalDate = $latestSubscription->getPaidUntil();
                    $membershipType = $latestSubscription->getIntervalCount() . $latestSubscription->getIntervalType();
                    $membershipStatus = $latestSubscription->getState();
                    $membershipRate = $latestSubscription->getTotalPrice();
                    $membershipFailedRenewalAttempts = $latestSubscription->getRenewalAttempt();

                    $membershipDetails = $membershipType . '|' . $membershipStatus . '|'. $membershipRate;

                    $membershipCancellationDate = $membershipCancellationReason = null;

                    if ($latestSubscription->getState() == Subscription::STATE_CANCELED) {
                        $membershipCancellationDate =
                            !empty($latestSubscription->getCanceledOn()) ?
                                $latestSubscription->getCanceledOn()->timestamp :
                                null;
                        $membershipCancellationReason = $latestSubscription->getCancellationReason();
                    }

                    $membershipSourceAppStore = false;

                    if (in_array(
                        $latestSubscription->getType(),
                        [
                            Subscription::TYPE_APPLE_SUBSCRIPTION,
                            Subscription::TYPE_GOOGLE_SUBSCRIPTION,
                        ]
                    )) {
                        $membershipSourceAppStore = true;
                    }
                } else if (empty($latestMembershipUserProduct->getExpirationDate())) {
                    $membershipDetails = 'lifetime';
                } else {
                    $latestMembershipProduct = $latestMembershipUserProduct->getProduct();
                    $membershipType = $latestMembershipProduct->getSubscriptionIntervalCount() . $latestMembershipProduct->getSubscriptionIntervalType();

                    $userOrders = $this->orderRepository->getUserOrdersForProduct(
                            $user->getId(),
                            $latestMembershipUserProduct->getProduct()
                        );

                    $membershipRate = 'unknown';

                    $latestMembershipOrder = null;

                    if (count($userOrders) == 1) {
                        $latestMembershipOrder = $userOrders[0];
                    } else if (count($userOrders) > 1) {
                        foreach ($userOrders as $order) {
                            if ($order->getCreatedAt()->format('Ym') == $latestMembershipUserProduct->getCreatedAt()->format('Ym')) {
                                $latestMembershipOrder = $order;
                            }
                        }
                    }

                    if ($latestMembershipOrder) {
                        foreach ($latestMembershipOrder->getOrderItems() as $orderItem) {
                            if ($orderItem->getProduct()->getSku() == $latestMembershipProduct->getSku()) {
                                $membershipRate = $orderItem->getFinalPrice();
                            }
                        }
                    }

                    $membershipDetails = $membershipType . '|' . $latestMembershipProduct->getType() . '|'. $membershipRate;
                }
            }

            $attributes += [
                $brand . '_membership_details' => $membershipDetails,
                $brand . '_membership_renewal-date' => !empty($membershipRenewalDate) ?
                    $membershipRenewalDate->timestamp : null,
                $brand . '_retention_failed-billing_membership-renewal-attempts' => $membershipFailedRenewalAttempts,
                $brand . '_membership_cancellation-date' => $membershipCancellationDate,
                $brand . '_membership_cancellation-reason' => $membershipCancellationReason,
                $brand . '_membership_latest-start-date' => !empty($membershipLatestAccessStartDate) ?
                    $membershipLatestAccessStartDate->timestamp : null,
                $brand . '_membership_first-start-date' => !empty($membershipFirstAccessStartDate) ?
                    $membershipFirstAccessStartDate->timestamp : null,
                $brand . '_membership_source_app-store' => $membershipSourceAppStore,
            ];
        }

        return $attributes;
    }
}
