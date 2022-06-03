<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Exception;
use HelpScout\Api\Exception\ConflictException;
use HelpScout\Api\Exception\RateLimitExceededException;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\EventDataSynchronizer\Services\HelpScoutSyncService;
use Railroad\RailHelpScout\Services\RailHelpScoutService;
use Throwable;

class SyncHelpScout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SyncHelpScout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all database users with helpscout';

    const RETRY_ATTEMPTS = 3;
    const SLEEP_DELAY = 600;

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(
        DatabaseManager $databaseManager,
        EcommerceEntityManager $ecommerceEntityManager,
        HelpScoutSyncService $helpScoutSyncService,
        RailHelpScoutService $railHelpScoutService
    ) {
        $this->info('Starting SyncHelpScout.');

        $usoraConnection = $databaseManager->connection(config('usora.database_connection_name'));
        $railhelpscoutConnection = $databaseManager->connection(config('railhelpscout.database_connection_name'));

        $done = 0;
        $total =
            $usoraConnection->table('usora_users')
                ->count();

        $usoraConnection->table('usora_users')
            ->orderBy('id', 'asc')
            ->chunk(
                25,
                function (Collection $userRows) use (
                    $railHelpScoutService,
                    $helpScoutSyncService,
                    $ecommerceEntityManager,
                    &$done,
                    $total,
                    $railhelpscoutConnection,
                    $usoraConnection
                ) {
                    $existingCustomersMap =
                        $railhelpscoutConnection->table('helpscout_customers')
                            ->whereIn(
                                'internal_id',
                                $userRows->pluck('id')
                                    ->toArray()
                            )
                            ->get()
                            ->pluck('internal_id')
                            ->mapWithKeys(function ($item) {
                                return [$item => true];
                            })
                            ->toArray();

                    foreach ($userRows as $userData) {
                        if (!isset($existingCustomersMap[$userData->id])) {
                            $this->syncUser(
                                $helpScoutSyncService,
                                $railHelpScoutService,
                                $ecommerceEntityManager,
                                $userData->id,
                                $userData->first_name,
                                $userData->last_name,
                                $userData->display_name,
                                $userData->email,
                                $userData->country,
                                $userData->city,
                                $userData->phone_number,
                                $userData->timezone,
                                $railhelpscoutConnection,
                                $usoraConnection
                            );
                        }

                        $done++;
                    }

                    $this->info('Done ' . $done . ' out of ' . $total);
                    $this->info('Real: ' . (memory_get_peak_usage(true) / 1024 / 1024) . " MiB\n\n");

                    $ecommerceEntityManager->flush();
                    $ecommerceEntityManager->clear();
                    $ecommerceEntityManager->getConnection()->ping();
                }
            );
    }

    /**
     * @throws Throwable
     */
    protected function syncUser(
        HelpScoutSyncService $helpScoutSyncService,
        RailHelpScoutService $railHelpScoutService,
        EcommerceEntityManager $ecommerceEntityManager,
        $usoraId,
        $firstName,
        $lastName,
        $displayName,
        $email,
        $country,
        $city,
        $phoneNumber,
        $timezone,
        $railhelpscoutConnection,
        $usoraConnection
    ) {
        $attempt = 1;

        $userAttributes =
            $helpScoutSyncService->getUsersAttributesById(
                $usoraId,
                $firstName,
                $displayName,
                $country,
                $city,
                $phoneNumber,
                $timezone
            );

        while ($attempt <= self::RETRY_ATTEMPTS) {
            try {
                $railHelpScoutService->createCustomer(
                    $usoraId,
                    $firstName,
                    $lastName,
                    $email,
                    $userAttributes
                );

                $this->info('Sync successful for user id ' . $usoraId . ', email: ' . $email);

                return;
            } catch (RateLimitExceededException $rateException) {
                $this->error(
                    'RateLimitExceededException raised when syncing user ' . $email
                    . ', sleeping for ' . self::SLEEP_DELAY . ' seconds'
                );

                $this->pause($ecommerceEntityManager, $railhelpscoutConnection, $usoraConnection);

                $attempt++;
            } catch (ConflictException $conflictException) {
                $this->error(
                    'ConflictException raised, user with usora id: ' . $usoraId . ' and email: ' . $email
                    . ' already has a helpscout customer entry'
                );

                return;
            } catch (Exception $ex) {
                error_log($ex);
                return;
            }
        }
    }

    protected function pause(
        EcommerceEntityManager $ecommerceEntityManager,
        $railhelpscoutConnection,
        $usoraConnection
    ) {
        $sleepDelayPerCycle = 10;
        $cycles = 60;

        while ($cycles >= 0) {
            sleep($sleepDelayPerCycle);

            if ($ecommerceEntityManager->getConnection()->ping() === false) {
                $ecommerceEntityManager->getConnection()->close();
                $ecommerceEntityManager->getConnection()->connect();
            }

            if (is_null($railhelpscoutConnection->getPdo())) {
                $railhelpscoutConnection->reconnect();
                $this->info('reconnecting railhelpscoutConnection db connection');
            }

            $railhelpscoutConnection->table('helpscout_customers')
                ->first();

            if (is_null($usoraConnection->getPdo())) {
                $usoraConnection->reconnect();
                $this->info('reconnecting usoraConnection db connection');
            }

            $usoraConnection->table('usora_users')
                ->first();

            $cycles--;
        }
    }
}
