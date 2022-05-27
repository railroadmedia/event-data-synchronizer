<?php

namespace Railroad\EventDataSynchronizer\Tests\Fixtures;

use Railroad\Railforums\Contracts\UserProviderInterface;

class TestingRailforumsUserProvider implements UserProviderInterface
{
    /**
     * @return mixed|\Railroad\Usora\Entities\User|null
     */
    public function getCurrentUser()
    {
        return null;
    }

    /**
     * @param $userId
     * @return mixed|string
     */
    public function getUserAccessLevel($userId)
    {
        return '';
    }

    /**
     * @param $userId
     * @return \Railroad\Usora\Entities\User
     */
    public function getUser($userId)
    {
        return null;
    }

    /**
     * @param array $userIds
     * @return array|\Railroad\Usora\Entities\User[]
     */
    public function getUsersByIds(array $userIds): array
    {
        return [];
    }

    /**
     * @param array $userIds
     * @return array
     */
    public function getUsersAccessLevel(array $userIds): array
    {
        return [];
    }

    /**
     * @param array $userIds
     * @return array
     */
    public function getUsersXPAndRank(array $userIds): array
    {
        return [];
    }

    /**
     * @param array $userIds
     * @return array
     */
    public function getAssociatedCoaches(array $userIds): array
    {
        return [];
    }
}
