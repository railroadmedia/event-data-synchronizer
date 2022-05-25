<?php

namespace Railroad\EventDataSynchronizer\Services;

use Illuminate\Support\Facades\DB;
use Railroad\Ecommerce\Entities\Product;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Repositories\ContentFollowsRepository;
use Railroad\Railcontent\Services\ContentService;

class UserMembershipFieldsService
{
    protected SubscriptionRepository $subscriptionRepository;
    protected UserProductRepository $userProductRepository;
    protected ProductRepository $productRepository;
    private ContentFollowsRepository $contentFollowsRepository;
    private ContentService $contentService;

    /**
     * @param  SubscriptionRepository  $subscriptionRepository
     * @param  UserProductRepository  $userProductRepository
     * @param  ProductRepository  $productRepository
     */
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository,
        ProductRepository $productRepository,
        ContentFollowsRepository $contentFollowsRepository,
        ContentService $contentService,
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
        $this->contentFollowsRepository = $contentFollowsRepository;
        $this->contentService = $contentService;
    }

    public function sync($userId)
    {
        $userProducts = $this->userProductRepository->getAllUsersProducts($userId);
        $representingUserProduct = $this->getUserProductThatRepresentsUsersMembership($userId, $userProducts);

        $membershipExpirationDate = $representingUserProduct->getExpirationDate();
        $isLifetimeMember = $representingUserProduct->getProduct()->getDigitalAccessTimeType() ==
            Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME;

        if ($isLifetimeMember) {
            $membershipExpirationDate = null;
        }

        // membership_expiration_date
        // is_lifetime_member
        // access_level
    }

    /**
     * @param $userId
     * @param  UserProduct[]  $usersProducts
     * @return UserProduct|null
     */
    public function getUserProductThatRepresentsUsersMembership($userId, array $usersProducts): ?UserProduct
    {
        $eligibleUserProducts = [];

        foreach ($usersProducts as $userProductIndex => $userProduct) {
            // make sure the product is a membership product
            if ($userProduct->getProduct()->getDigitalAccessType() !==
                Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS ||
                $userProduct->getUser()->getId() !== $userId) {
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
            if (!empty($latestMembershipUserProductToSync) &&
                empty($eligibleUserProduct->getExpirationDate()) &&
                $eligibleUserProduct->getProduct()->getDigitalAccessTimeType() ==
                Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME) {
                $latestMembershipUserProductToSync = $eligibleUserProduct;

                break;
            }

            // if this product expiration date is further in the past than whatever is currently set, skip it
            if (!empty($latestMembershipUserProductToSync) &&
                ($latestMembershipUserProductToSync->getExpirationDate() <
                    $eligibleUserProduct->getExpirationDate())) {
                $latestMembershipUserProductToSync = $eligibleUserProduct;
            }
        }

        if (!empty($latestMembershipUserProductToSync)) {
            return $latestMembershipUserProductToSync;
        }

        return null;
    }

    /**
     * @param $userId
     * @return string
     */
    public function getAccessLevelName($userId)
    {
        if (empty($userId)) {
            return 'pack';
        }

        if ($this->isHouseCoach($userId)) {
            return 'house-coach';
        }

        if (self::isCoach($userId)) {
            return 'coach';
        }

        if ($user->getPermissionLevel() === 'administrator') {
            return 'team';
        }

        if (self::isEdgeLifetime($userId)) {
            return 'lifetime';
        }

        if (self::isEdge($userId)) {
            return 'edge';
        }

        return 'pack';
    }

    /**
     * @param $userId
     * @return bool|mixed
     */
    public function isHouseCoach($userId)
    {
        $associatedCoach = $this->getAssociatedCoaches([$userId]);

        return
            (!empty($associatedCoach) &&
                array_key_exists($userId, $associatedCoach) &&
                ($associatedCoach[$userId]['is_house_coach'] == "1"));
    }

    /**
     * @param $userId
     * @return bool|mixed
     */
    public function isCoach($userId)
    {
        $associatedCoach = $this->getAssociatedCoaches([$userId]);

        return !empty($associatedCoach) && array_key_exists($userId, $associatedCoach);
    }

    /**
     * @param  array  $userIds
     * @return array
     */
    public function getAssociatedCoaches(array $userIds)
    {
        $includedFields = [];
        $associatedUsers = [];

        foreach ($userIds ?? [] as $userId) {
            $includedFields[] = 'associated_user_id,'.$userId;
        }

        $instructors =
            $this->contentService
                ->getFiltered(
                    1,
                    'null',
                    '-published_on',
                    ['instructor'],
                    [],
                    [],
                    [],
                    $includedFields
                );

        foreach ($instructors->results() as $instructor) {
            $associatedUsers[$instructor->fetch('fields.associated_user_id')] = [
                'id' => $instructor['id'],
                'url' => $instructor->fetch('url', ''),
                'is_house_coach' => $instructor->fetch('fields.is_house_coach', 0),
            ];
        }

        return $associatedUsers;
    }
}
