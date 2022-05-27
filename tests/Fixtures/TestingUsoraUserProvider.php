<?php

namespace Railroad\EventDataSynchronizer\Tests\Fixtures;

use Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface;
use Railroad\Usora\Managers\UsoraEntityManager;
use Railroad\Usora\Repositories\UserRepository;

class TestingUsoraUserProvider implements UserProviderInterface
{
    CONST RESOURCE_TYPE = 'user';

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var UsoraEntityManager
     */
    private $usoraEntityManager;

    /**
     * UsoraTestingUserProvider constructor.
     *
     * @param UserRepository $userRepository
     * @param UsoraEntityManager $usoraEntityManager
     */
    public function __construct(UserRepository $userRepository, UsoraEntityManager $usoraEntityManager)
    {
        $this->userRepository = $userRepository;
        $this->usoraEntityManager = $usoraEntityManager;
    }

    /**
     * @param $entity
     * @param string $relationName
     * @param array $data
     */
    public function hydrateTransDomain($entity, string $relationName, array $data): void
    {
        $setterName = app('DoctrineInflector')->camelize('set' . ucwords($relationName));

        if (isset($data['data']['type']) &&
            $data['data']['type'] === self::RESOURCE_TYPE &&
            isset($data['data']['id']) &&
            is_object($entity) &&
            method_exists($entity, $setterName)) {

            $user = $this->userRepository->find($data['data']['id']);

            call_user_func([$entity, $setterName], $user);
        }
    }

    /**
     * @param string $resourceType
     * @return bool
     */
    public function isTransient(string $resourceType): bool
    {
        return $resourceType !== self::RESOURCE_TYPE;
    }
}
