<?php

namespace Railroad\EventDataSynchronizer\Providers;

use Carbon\Carbon;

interface UserProviderInterface
{
    /**
     * @param int $userId
     * @return bool
     */
    public function isAdministrator(int $userId): bool;

    /**
     * @param int $userId
     * @param Carbon|null $membershipExpirationDate
     * @param bool $isLifetimeMember
     * @param string $accessLevel
     * @param bool $isPackOwner
     * @param string|null $membershipLevel
     * @return bool
     */
    public function saveMembershipData(
        int $userId,
        Carbon $membershipExpirationDate = null,
        bool $isLifetimeMember,
        string $accessLevel,
        bool $isPackOwner,
        string $membershipLevel = null
    ): bool;
}
