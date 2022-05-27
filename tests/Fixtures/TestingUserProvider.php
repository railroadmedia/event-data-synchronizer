<?php

namespace Railroad\EventDataSynchronizer\Tests\Fixtures;

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Railroad\EventDataSynchronizer\Providers\UserProviderInterface;

class TestingUserProvider implements UserProviderInterface
{
    private DatabaseManager $databaseManager;

    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function isAdministrator(int $userId): bool
    {
        return $this->databaseManager->connection(config('event-data-synchronizer.users_database_connection_name'))
            ->table(config('event-data-synchronizer.users_table_name'))
            ->where('id', $userId)
            ->where('permission_level', 'administrator')
            ->exists();
    }

    public function saveMembershipData(
        int $userId,
        ?Carbon $membershipExpirationDate,
        bool $isLifetimeMember,
        string $accessLevel
    ): bool {
        return $this->databaseManager->connection(config('event-data-synchronizer.users_database_connection_name'))
                ->table(config('event-data-synchronizer.users_table_name'))
                ->where('id', $userId)
                ->update(
                    [
                        'membership_expiration_date' => !empty($membershipExpirationDate) ?
                            $membershipExpirationDate->toDateTimeString() : null,
                        'is_lifetime_member' => $isLifetimeMember,
                        'access_level' => $accessLevel,
                    ]
                ) > 0;
    }

}
