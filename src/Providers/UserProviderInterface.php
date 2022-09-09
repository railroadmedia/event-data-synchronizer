<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Carbon\Carbon;

interface UserProviderInterface
{
    /**
     * @param int $userId
     * @return bool
     */
    public function isAdministrator(int $userId)
    : bool;

    /**
     * @param int $userId
     * @param Carbon $membershipExpirationDate
     * @param bool $isLifetimeMember
     * @param string $accessLevel
     * @param bool $isPackOwner
     * @return bool
     */
    public function saveMembershipData(
        int $userId,
        Carbon $membershipExpirationDate,
        bool $isLifetimeMember,
        string $accessLevel,
        bool $isPackOwner
    )
    : bool;

    /**
     * @param int $userId
     * @param int $totalXp
     * @return bool
     */
    public function saveExperiencePoints(int $userId, int $totalXp)
    : bool;
}
