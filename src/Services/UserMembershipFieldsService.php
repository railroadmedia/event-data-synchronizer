<?php

namespace Railroad\EventDataSynchronizer\Services;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\Product;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\EventDataSynchronizer\Providers\UserProviderInterface;
use Railroad\Railcontent\Services\ContentService;

class UserMembershipFieldsService
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
     * @var ContentService
     */
    private $contentService;

    /**
     * @var UserProviderInterface
     */
    private $userProvider;

    private $instructorsCache = null;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository,
        ProductRepository $productRepository,
        ContentService $contentService,
        UserProviderInterface $userProvider
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
        $this->contentService = $contentService;
        $this->userProvider = $userProvider;
    }

    public function syncUserIds(array $userIds): bool
    {
        $qb = $this->userProductRepository->createQueryBuilder('up');

        $qb->select(['up', 'p'])
            ->join('up.product', 'p')
            ->andWhere(
                $qb->expr()
                    ->in('up.user', ':userIds')
            )
            ->setParameter('userIds', $userIds);

        /**
         * @var $allUsersUserProducts UserProduct[]
         */
        $allUsersUserProducts = $qb->getQuery()->getResult();

        $allUsersUserProductsGroupedByUserId = [];

        foreach ($allUsersUserProducts as $allUsersUserProduct) {
            $allUsersUserProductsGroupedByUserId[$allUsersUserProduct->getUser()->getId()][] = $allUsersUserProduct;
        }

        $associatedCoachesByUserId = $this->getAssociatedCoaches($userIds);

        foreach ($userIds as $userId) {
            $this->sync(
                $userId,
                $allUsersUserProductsGroupedByUserId[$userId] ?? [],
                $associatedCoachesByUserId[$userId] ?? []
            );
        }

        return true;
    }

    /**
     * @param $userId
     * @return bool
     */
    public function sync($userId, array $userProducts = null, array $associatedCoaches = null): bool
    {
        if (!isset($userProducts)) {
            $userProducts = $this->userProductRepository->getAllUsersProducts($userId);
        }

        $representingUserProduct = $this->getUserProductThatRepresentsUsersMembership($userId, $userProducts);

        if (!empty($representingUserProduct)) {
            $membershipExpirationDate = !empty($representingUserProduct->getExpirationDate()) ?
                Carbon::instance(
                    $representingUserProduct->getExpirationDate()
                ) : $representingUserProduct->getExpirationDate();
            $isLifetimeMember = $representingUserProduct->isValid() &&
                $representingUserProduct->getProduct()->getDigitalAccessTimeType() ==
                Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME;
        } else {
            $membershipExpirationDate = null;
            $isLifetimeMember = false;
        }

        $ownsPacks = false;

        foreach ($userProducts as $userProduct) {
            if ($userProduct->getProduct()->getDigitalAccessType(
                ) == Product::DIGITAL_ACCESS_TYPE_SPECIFIC_CONTENT_ACCESS &&
                $userProduct->getProduct()->getDigitalAccessTimeType() == Product::DIGITAL_ACCESS_TIME_TYPE_ONE_TIME &&
                $userProduct->isValid()) {
                $ownsPacks = true;
                break;
            }
        }

        $accessLevel = $this->getAccessLevelName(
            $userId,
            $isLifetimeMember,
            !empty($representingUserProduct) && $representingUserProduct->isValid(),
            $membershipExpirationDate,
            $ownsPacks,
            $associatedCoaches
        );

        return $this->userProvider->saveMembershipData(
            $userId,
            $membershipExpirationDate,
            $isLifetimeMember,
            $accessLevel,
            $ownsPacks
        );
    }

    /**
     * @param $userId
     * @param UserProduct[] $usersProducts
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
     * The value of this should be not used for anything other than visual purposes. It does not always represent the
     * state of the users membership exactly.
     *
     * @param $userId
     * @param bool $isLifetime
     * @param bool $isAMember
     * @param Carbon|null $membershipExpirationDate
     * @param bool $ownsPacks
     * @return string
     */
    public function getAccessLevelName(
        $userId,
        bool $isLifetime,
        bool $isAMember,
        Carbon $membershipExpirationDate = null,
        bool $ownsPacks,
        array $associatedCoaches = null
    ): string {
        if (empty($userId)) {
            return '';
        }

        if ($this->isHouseCoach($userId, $associatedCoaches)) {
            return 'house-coach';
        }

        if ($this->isCoach($userId, $associatedCoaches)) {
            return 'coach';
        }

        if ($this->userProvider->isAdministrator($userId)) {
            return 'team';
        }

        if ($isLifetime) {
            return 'lifetime';
        }

        if ($isAMember && (!empty($membershipExpirationDate) && $membershipExpirationDate > Carbon::now())) {
            return 'member';
        }

        if ($ownsPacks) {
            return 'pack';
        }

        if (!empty($membershipExpirationDate) && $membershipExpirationDate < Carbon::now()) {
            return 'expired';
        }

        return '';
    }

    /**
     * @param $userId
     * @return bool
     */
    public function isHouseCoach($userId, array $associatedCoaches = null): bool
    {
        if (!isset($associatedCoaches)) {
            $associatedCoaches = $this->getAssociatedCoaches([$userId]);
        }

        return
            (!empty($associatedCoaches) &&
                array_key_exists($userId, $associatedCoaches) &&
                ($associatedCoaches[$userId]['is_house_coach'] == "1"));
    }

    /**
     * @param $userId
     * @return bool
     */
    public function isCoach($userId, array $associatedCoaches = null): bool
    {
        if (!isset($associatedCoaches)) {
            $associatedCoaches = $this->getAssociatedCoaches([$userId]);
        }

        return !empty($associatedCoaches) && array_key_exists($userId, $associatedCoaches);
    }

    /**
     * @param array $userIds
     * @return array
     */
    public function getAssociatedCoaches(array $userIds): array
    {
        $includedFields = [];
        $associatedUsers = [];

        foreach ($userIds ?? [] as $userId) {
            $includedFields[] = 'associated_user_id,' . $userId;
        }

        if (!isset($this->instructorsCache)) {
            $this->instructorsCache =
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
        }

        $instructors = $this->instructorsCache;

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
