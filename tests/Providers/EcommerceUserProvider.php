<?php

namespace Railroad\EventDataSynchronizer\Tests\Providers;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use League\Fractal\TransformerAbstract;
use Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface as ArrayHydratorUserProviderInterface;
use Railroad\Ecommerce\Contracts\UserProviderInterface;
use Railroad\Ecommerce\Entities\User;
use Railroad\Ecommerce\Transformers\UserTransformer;
use Railroad\Usora\Entities\User as UsoraUser;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Managers\UsoraEntityManager;
use Railroad\Usora\Repositories\UserRepository;
use Tymon\JWTAuth\JWTAuth;

class EcommerceUserProvider implements UserProviderInterface, ArrayHydratorUserProviderInterface
{
    CONST RESOURCE_TYPE = 'user';

    /**
     * @var JWTAuth
     */
    private $jwtAuth;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var UsoraEntityManager
     */
    private $usoraEntityManager;

    /**
     * EcommerceUserProvider constructor.
     *
     * @param JWTAuth $jwtAuth
     * @param UsoraEntityManager $usoraEntityManager
     * @param UserRepository $userRepository
     */
    public function __construct(
        JWTAuth $jwtAuth,
        UsoraEntityManager $usoraEntityManager,
        UserRepository $userRepository
    )
    {
        $this->jwtAuth = $jwtAuth;
        $this->userRepository = $userRepository;
        $this->usoraEntityManager = $usoraEntityManager;
    }

    /**
     * @param int $id
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        $usoraUser = $this->userRepository->find($id);

        if ($usoraUser) {
            return new User($usoraUser->getId(), $usoraUser->getEmail());
        }

        return null;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getUsersByIds(array $ids): array
    {
        $qb = $this->userRepository->createQueryBuilder('u');

        $qb
            ->where(
                $qb->expr()
                    ->in('u.id', ':userIds')
            )
            ->setParameter('userIds', $ids);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param User $user
     * @return int
     */
    public function getUserId(User $user): int
    {
        return $user->getId();
    }

    /**
     * @return User|null
     */
    public function getCurrentUser(): ?User
    {
        if (!auth()->id()) {
            return null;
        }

        return $this->getUserById(auth()->id());
    }

    /**
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        return auth()->id();
    }

    /**
     * @return TransformerAbstract
     */
    public function getUserTransformer(): TransformerAbstract
    {
        return new UserTransformer();
    }

    /**
     * @param $entity
     * @param string $relationName
     * @param array $data
     */
    public function hydrateTransDomain($entity, string $relationName, array $data): void
    {
        $setterName = Inflector::camelize('set' . ucwords($relationName));

        if (isset($data['data']['type']) &&
            $data['data']['type'] === self::RESOURCE_TYPE &&
            isset($data['data']['id']) &&
            is_object($entity) &&
            method_exists($entity, $setterName)) {

            $user = $this->getUserById($data['data']['id']);

            call_user_func([$entity, $setterName], $user);
        }

        // else some exception should be thrown
    }

    /**
     * @param string $resourceType
     * @return bool
     */
    public function isTransient(string $resourceType): bool
    {
        return $resourceType !== self::RESOURCE_TYPE;
    }

    /**
     * @param string $email
     * @param string $password
     * @return User|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createUser(string $email, string $password): ?User
    {
        $usoraUser = new UsoraUser();

        $usoraUser->setEmail($email);
        $parts = explode('@', $email);
        $usoraUser->setDisplayName($parts[0] . rand(10000, 99999));
        $usoraUser->setPassword($password);

        $this->usoraEntityManager->persist($usoraUser);
        $this->usoraEntityManager->flush();
        
        event(new UserCreated($usoraUser));

        return new User($usoraUser->getId(), $usoraUser->getEmail());
    }

    /**
     * @param string $email
     *
     * @return bool
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function checkEmailExists(string $email): bool
    {
        $user = $this->userRepository->findOneByEmail($email);

        return ($user != null);
    }

    /**
     * @param User $user
     * @return string
     */
    public function getUserAuthToken(User $user): string
    {
        return $this->jwtAuth->fromUser($user);
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        $usoraUser = $this->userRepository->findOneByEmail($email);

        if ($usoraUser) {
            return new User($usoraUser->getId(), $usoraUser->getEmail());
        }

        return null;
    }
}
