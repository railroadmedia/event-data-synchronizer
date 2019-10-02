<?php

namespace Railroad\EventDataSynchronizer\Listeners\Maropost;

use Exception;
use Railroad\Ecommerce\Entities\Subscription;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Maropost\Jobs\SyncContact;
use Railroad\Maropost\ValueObjects\ContactVO;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;

class MaropostEventListener
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var UserProductRepository
     */
    private $userProductRepository;

    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;

    /**
     * MaropostEventListener constructor.
     *
     * @param  UserRepository  $userRepository
     * @param  UserProductRepository  $userProductRepository
     * @param  SubscriptionRepository  $subscriptionRepository
     */
    public function __construct(
        UserRepository $userRepository,
        UserProductRepository $userProductRepository,
        SubscriptionRepository $subscriptionRepository
    ) {
        $this->userRepository = $userRepository;
        $this->userProductRepository = $userProductRepository;
        $this->subscriptionRepository = $subscriptionRepository;
    }

    // todo: only send one sync request at the and when the response is create instead of many multiple API calls

    /**
     * @param  UserCreated  $userCreated
     */
    public function handleUserCreated(UserCreated $userCreated)
    {
        // turning this off for now since we only need to listen for user email updates
        
//        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
//            return;
//        }
//
//        try {
//            $this->syncUser($userCreated->getUser()->getId());
//        } catch (Exception $exception) {
//            error_log($exception);
//        }
    }

    /**
     * @param  UserUpdated  $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
            return;
        }

        try {
            $this->syncUser($userUpdated->getNewUser()->getId());
        } catch (Exception $exception) {
            error_log($exception);
        }
    }

    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
            return;
        }

        try {
            $this->syncUser($subscriptionCreated->getSubscription()->getUser()->getId());
        } catch (Exception $exception) {
            error_log($exception);
        }
    }

    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
            return;
        }

        try {
            $this->syncUser($subscriptionUpdated->getNewSubscription()->getUser()->getId());
        } catch (Exception $exception) {
            error_log($exception);
        }
    }

    /**
     * @param  UserProductUpdated  $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
            return;
        }

        try {
            $this->syncUser($userProductUpdated->getNewUserProduct()->getUser()->getId());
        } catch (Exception $exception) {
            error_log($exception);
        }
    }

    /**
     * @param  UserProductDeleted  $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
            return;
        }

        try {
            $this->syncUser($userProductDeleted->getUserProduct()->getUser()->getId());
        } catch (Exception $exception) {
            error_log($exception);
        }
    }

    /**
     * @param  UserProductCreated  $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        if (config('event-data-synchronizer.maropost_disable_syncing', false)) {
            return;
        }

        try {
            $this->syncUser($userProductCreated->getUserProduct()->getUser()->getId());
        } catch (Exception $exception) {
            error_log($exception);
        }
    }

    /**
     * @param $userId
     */
    public function syncUser($userId)
    {
        dispatch(new SyncContact($this->getContactVOToSync($userId)));
    }

    /**
     * @param $userId
     * @return ContactVO
     */
    public function getContactVOToSync($userId)
    {
        $user = $this->userRepository->find($userId);

        /**
         * @var $allUsersProducts UserProduct[]
         */
        $allUsersProducts = $this->userProductRepository->getAllUsersProducts($userId);
        $allUsersSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($userId);

        $oneTimeProductBrandsSkuTagMap =
            config('event-data-synchronizer.one_time_product_sku_maropost_tag_mapping', []);
        $membershipTypeBrandsSkuTagMap =
            config('event-data-synchronizer.membership_type_product_sku_maropost_tag_mapping', []);

        $addTags = [];
        $removeTags = [];
        $listIdsToSubscribeTo = [];

        // find the membership tag for the user
        // a user can only have one membership type tag per brand
        // generally the options are: 1 month recurring, 6 month recurring, 1 year recurring, lifetime

        foreach ($membershipTypeBrandsSkuTagMap as $brand => $recurringOrLifetime) {
            $membershipTagToAdd = null;

            // check if this user is lifetime, if so only add the lifetime tag and skip checking their subscriptions
            foreach ($recurringOrLifetime['lifetime'] as $productSku => $tagName) {
                if ($this->userHasProductSku($allUsersProducts, $brand, $productSku)) {
                    $membershipTagToAdd = $tagName;
                }
            }

            // if not lifetime, figure out what membership type they have based on subscriptions
            if (empty($membershipTagToAdd)) {
                // figure out which recurring type this user is and add tags accordingly
                foreach ($allUsersSubscriptions as $userSubscription) {
                    if (!empty($userSubscription->getProduct()) &&
                        $userSubscription->getProduct()->getBrand() == $brand &&
                        !empty($recurringOrLifetime['recurring'][$userSubscription->getProduct()->getSku()]) &&
                        $userSubscription->getIsActive() &&
                        $userSubscription->getState() == Subscription::STATE_ACTIVE) {

                        $membershipTagToAdd =
                            $recurringOrLifetime['recurring'][$userSubscription->getProduct()->getSku()];

                        break;
                    }
                }

                // if they don't have an active subscription we still want to sync the membership type if they have
                // an expired or cancelled subscription
                if (empty($membershipTagToAdd)) {
                    foreach ($allUsersSubscriptions as $userSubscription) {
                        if (!empty($userSubscription->getProduct()) &&
                            $userSubscription->getProduct()->getBrand() == $brand &&
                            !empty($recurringOrLifetime['recurring'][$userSubscription->getProduct()->getSku()])) {

                            $membershipTagToAdd =
                                $recurringOrLifetime['recurring'][$userSubscription->getProduct()->getSku()];

                            break;
                        }
                    }
                }
            }

            if (!empty($membershipTagToAdd)) {
                $addTags[] = $membershipTagToAdd;
            }

            // remove all other tags
            foreach (array_merge($recurringOrLifetime['lifetime'], $recurringOrLifetime['recurring']) as $productSku =>
                     $tagName) {
                if (!in_array($tagName, $addTags)) {
                    $removeTags[] = $tagName;
                }
            }
        }

        // product sku tags
        foreach ($oneTimeProductBrandsSkuTagMap as $brand => $skuTagMap) {
            foreach ($skuTagMap as $sku => $tagName) {

                if ($this->userHasProductSku($allUsersProducts, $brand, $sku)) {
                    $addTags[] = $tagName;
                } elseif (!in_array($tagName, $addTags)) {
                    $removeTags[] = $tagName;
                }

            }
        }

        // active membership tag
        foreach (config('event-data-synchronizer.maropost_member_active_tag') as $brand => $tagName) {
            $hasMembershipAccess = false;

            foreach (config('event-data-synchronizer.' . $brand . '_membership_product_ids', []) as $productId) {
                if ($this->userHasProductId($allUsersProducts, $brand, $productId)) {
                    $hasMembershipAccess = true;
                }
            }

            if ($hasMembershipAccess) {
                $addTags[] = config('event-data-synchronizer.maropost_member_active_tag')[$brand];
            } else {
                $removeTags[] = config('event-data-synchronizer.maropost_member_active_tag')[$brand];
            }
        }

        // expired membership tag
        foreach (config('event-data-synchronizer.maropost_member_active_tag') as $brand => $tagName) {
            $hasExpiredAccess = false;

            foreach (config('event-data-synchronizer.' . $brand . '_membership_product_ids', []) as $productId) {
                if ($this->userHasInvalidProductId($allUsersProducts, $brand, $productId)) {
                    $hasExpiredAccess = true;
                }
            }

            if ($hasExpiredAccess &&
                !in_array(config('event-data-synchronizer.maropost_member_active_tag')[$brand], $addTags)) {
                $addTags[] = config('event-data-synchronizer.maropost_member_expired_tag')[$brand];
            } else {
                $removeTags[] = config('event-data-synchronizer.maropost_member_expired_tag')[$brand];
            }
        }

        // brand lists to subscribe to
        foreach (config('event-data-synchronizer.maropost_product_brand_list_id_map') as $brand => $listId) {
            if ($this->userHasOrHasAnyProductUnderBrand($allUsersProducts, $brand)) {
                $listIdsToSubscribeTo[] = $listId;
            }
        }

        // remove all addTags from removeTags if they are set
        foreach ($addTags as $addTag) {
            foreach ($removeTags as $removeTagIndex => $removeTag) {
                if ($addTag == $removeTag) {
                    unset($removeTags[$removeTagIndex]);
                }
            }
        }

        return new ContactVO(
            $user->getEmail(),
            $user->getFirstName(),
            $user->getLastName(),
            [config('event-data-synchronizer.maropost_user_id_custom_field_name', 'user_id') => $user->getId()],
            $addTags,
            $removeTags,
            $listIdsToSubscribeTo
        );
    }

    /**
     * @param  UserProduct[]  $allUsersProducts
     * @param $brand
     * @param $sku
     *
     * @return bool
     */
    private function userHasProductSku(array $allUsersProducts, $brand, $sku)
    {
        foreach ($allUsersProducts as $userProduct) {
            $product = $userProduct->getProduct();

            if ($product->getSku() == $sku && $product->getBrand() == $brand && $userProduct->isValid()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  UserProduct[]  $allUsersProducts
     * @param $brand
     * @param $id
     * @return bool
     */
    private function userHasProductId(array $allUsersProducts, $brand, $id)
    {
        foreach ($allUsersProducts as $userProduct) {
            $product = $userProduct->getProduct();

            if ($product->getId() == $id && $product->getBrand() == $brand && $userProduct->isValid()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  UserProduct[]  $allUsersProducts
     * @param $brand
     * @return bool
     */
    private function userHasOrHasAnyProductUnderBrand(array $allUsersProducts, $brand)
    {
        foreach ($allUsersProducts as $userProduct) {
            $product = $userProduct->getProduct();

            if ($product->getBrand() == $brand) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  UserProduct[]  $allUsersProducts
     * @param $brand
     * @param $id
     * @return bool
     */
    private function userHasInvalidProductId(array $allUsersProducts, $brand, $id)
    {
        foreach ($allUsersProducts as $userProduct) {
            $product = $userProduct->getProduct();

            if ($product->getId() == $id && $product->getBrand() == $brand && !$userProduct->isValid()) {
                return true;
            }
        }

        return false;
    }
}