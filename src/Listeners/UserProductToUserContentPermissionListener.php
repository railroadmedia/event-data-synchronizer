<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityRepository;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Entities\Content;
use Railroad\Railcontent\Entities\Permission;
use Railroad\Railcontent\Entities\UserPermission;
use Railroad\Railcontent\Managers\RailcontentEntityManager;
use Railroad\Resora\Events\Created;
use Railroad\Resora\Events\Updated;
use Railroad\Usora\Repositories\UserRepository;

class UserProductToUserContentPermissionListener
{
    /**
     * @var UserProductRepository
     */
    private $userProductRepository;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ObjectRepository|EntityRepository
     */
    private $permissionRepository;

    /**
     * @var ObjectRepository|EntityRepository
     */
    private $userPermissionsRepository;

    /**
     * @var RailcontentEntityManager
     */
    private $railcontentEntityManager;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * UserProductToUserContentPermissionListener constructor.
     *
     * @param UserProductRepository $userProductRepository
     * @param ProductRepository $productRepository
     * @param RailcontentEntityManager $railcontentEntityManager
     * @param UserRepository $userRepository
     */
    public function __construct(
        UserProductRepository $userProductRepository,
        ProductRepository $productRepository,
        RailcontentEntityManager $railcontentEntityManager,
        UserRepository $userRepository
    ) {
        $this->railcontentEntityManager = $railcontentEntityManager;
        $this->userProductRepository = $userProductRepository;
        $this->productRepository = $productRepository;
        $this->userRepository = $userRepository;

        $this->permissionRepository = $this->railcontentEntityManager->getRepository(Permission::class);
        $this->userPermissionsRepository = $this->railcontentEntityManager->getRepository(UserPermission::class);

    }

    /**
     * @param Created $createdEvent
     */
    public function handleCreated(UserProductCreated $createdEvent)
    {
        $this->syncUserId(
            $createdEvent->getUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleUpdated(UserProductUpdated $updatedEvent)
    {
        $this->syncUserId(
            $updatedEvent->getNewUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleDeleted(UserProductDeleted $deletedEvent)
    {
        $this->syncUserId(
            $deletedEvent->getUserProduct()
                ->getUser()
                ->getId()
        );
    }

    public function syncUserId($userId)
    {
        $allUsersProducts = $this->userProductRepository->getAllUsersProducts($userId);

        $permissionsToCreate = [];

        foreach ($allUsersProducts as $allUsersProduct) {
            $permissionName = config(
                'event-data-synchronizer.ecommerce_product_sku_to_content_permission_name_map.' .
                $allUsersProduct->getProduct()
                    ->getSku()
            );

            if (empty($permissionName)) {
                continue;
            }

            // we need to check by brand as well since some permissions across brands have the same name
            $permissionArrayKey =
                $permissionName .
                '|' .
                $allUsersProduct->getProduct()
                    ->getBrand();

            if (!array_key_exists($permissionArrayKey, $permissionsToCreate)) {

                $permissionsToCreate[$permissionArrayKey] = [
                    'expiration_date' => $allUsersProduct->getExpirationDate(),
                    'start_date' => $allUsersProduct->getStartDate(),
                ];

            } elseif ($allUsersProduct->getExpirationDate() === null) {

                $permissionsToCreate[$permissionArrayKey] = [
                    'expiration_date' => $allUsersProduct->getExpirationDate(),
                    'start_date' => $allUsersProduct->getStartDate(),
                ];

            } elseif (isset($permissionsToCreate[$permissionArrayKey]) &&
                $permissionsToCreate[$permissionArrayKey] !== null) {

                if (Carbon::parse($permissionsToCreate[$permissionArrayKey]['expiration_date']) <
                    $allUsersProduct->getExpirationDate()) {
                    $permissionsToCreate[$permissionArrayKey] = [
                        'expiration_date' => $allUsersProduct->getExpirationDate(),
                        'start_date' => $allUsersProduct->getStartDate(),
                    ];
                }
            }
        }

        foreach ($permissionsToCreate as $permissionNameAndBrandToSync => $dates) {

            $permissionNameToSync = explode('|', $permissionNameAndBrandToSync)[0];
            $permissionBrandToSync = explode('|', $permissionNameAndBrandToSync)[1];
            $expirationDate = $dates['expiration_date'];
            $startDate =
                $dates['start_date']
                ??
                Carbon::now()
                    ->toDateTimeString();

            $permission =
                $this->permissionRepository->createQueryBuilder('p')
                    ->where('p.name = :name')
                    ->andWhere('p.brand = :brand')
                    ->setParameter('name', $permissionNameToSync)
                    ->setParameter('brand', $permissionBrandToSync)
                    ->getQuery()
                    ->getOneOrNullResult();

            if (!$permission) {
                continue;
            }

            $existingPermission = $this->userPermissionsRepository->userPermission($userId, $permission);

            if (!($existingPermission)) {

                $user = $this->userRepository->find($userId);

                $existingPermission = new UserPermission();
                $existingPermission->setUser($user);
                $existingPermission->setPermission($permission);
                $existingPermission->setStartDate(Carbon::now());
                $existingPermission->setExpirationDate($expirationDate);
                $existingPermission->setCreatedAt(Carbon::now());

                $this->railcontentEntityManager->persist($existingPermission);
                $this->railcontentEntityManager->flush();

            } else {

                $existingPermission->setExpirationDate($expirationDate);

                $this->railcontentEntityManager->persist($existingPermission);
                $this->railcontentEntityManager->flush();
            }
        }

        // clear the cache
        $this->railcontentEntityManager->getCache()
            ->evictEntityRegion(Content::class);
    }
}
