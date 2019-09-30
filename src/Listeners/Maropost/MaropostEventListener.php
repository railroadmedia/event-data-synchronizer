<?php

namespace Railroad\EventDataSynchronizer\Listeners\Maropost;

use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Maropost\Jobs\SyncContact;
use Railroad\Maropost\ValueObjects\ContactVO;
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
     * MaropostEventListener constructor.
     *
     * @param  UserRepository  $userRepository
     */
    public function __construct(UserRepository $userRepository, UserProductRepository $userProductRepository)
    {
        $this->userRepository = $userRepository;
        $this->userProductRepository = $userProductRepository;
    }

    /**
     * @param  UserProductUpdated  $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $userProduct = $userProductUpdated->getNewUserProduct();

        $this->syncUser($userProduct->getUser()->getId());
    }

    /**
     * @param  UserProductDeleted  $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        $userProduct = $userProductDeleted->getUserProduct();

        $this->syncUser($userProduct->getUser()->getId());
    }

    /**
     * @param  UserProductCreated  $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $userProduct = $userProductCreated->getUserProduct();

        $this->syncUser($userProduct->getUser()->getId());
    }

    /**
     * @param $userId
     */
    public function syncUser($userId)
    {
        $user = $this->userRepository->find($userId);

        /**
         * @var $allUsersProducts UserProduct[]
         */
        $allUsersProducts = $this->userProductRepository->getAllUsersProducts($userId);

        $brandsSkuTagMap = config('event-data-synchronizer.product_sku_maropost_tag_mapping', []);

        $addTags = [];
        $removeTags = [];
        $listIdsToSubscribeTo = [];

        // product sku tags
        foreach ($brandsSkuTagMap as $brand => $skuTagMap) {
            foreach ($skuTagMap as $sku => $tagName) {

                if ($this->userHasProductSku($allUsersProducts, $brand, $sku)) {
                    $addTags[] = $tagName;
                } elseif (!in_array($tagName, $addTags)) {
                    $removeTags[] = $tagName;
                }

            }
        }

        // active/expired membership tags
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

        // brand lists to subscribe to
        foreach (config('event-data-synchronizer.maropost_product_brand_list_id_map') as $brand => $listId) {
            if ($this->userHasOrHasAnyProductUnderBrand($allUsersProducts, $brand)) {
                $listIdsToSubscribeTo[] = $listId;
            }
        }

        $contact = new ContactVO(
            $user->getEmail(),
            $user->getFirstName(),
            $user->getLastName(),
            [config('event-data-synchronizer.maropost_user_id_custom_field_name', 'user_id') => $user->getId()],
            $addTags,
            $removeTags,
            $listIdsToSubscribeTo
        );

        dispatch(new SyncContact($contact));
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
}