<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use HelpScout\Api\Exception\ConflictException;
use HelpScout\Api\Exception\RateLimitExceededException;
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
     * @var DatabaseManager
     */
    private $databaseManager;

    /**
     * @var EcommerceEntityManager
     */
    private $ecommerceEntityManager;

    /**
     * @var HelpScoutSyncService
     */
    private $helpScoutSyncService;

    /**
     * @var RailHelpScoutService
     */
    private $railHelpScoutService;

    /**
     * SyncHelpScout constructor.
     *
     * @param DatabaseManager $databaseManager
     * @param EcommerceEntityManager $ecommerceEntityManager
     * @param HelpScoutSyncService $helpScoutSyncService
     * @param RailHelpScoutService $railHelpScoutService
     *
     */
    public function __construct(
        DatabaseManager $databaseManager,
        EcommerceEntityManager $ecommerceEntityManager,
        HelpScoutSyncService $helpScoutSyncService,
        RailHelpScoutService $railHelpScoutService
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->ecommerceEntityManager = $ecommerceEntityManager;
        $this->helpScoutSyncService = $helpScoutSyncService;
        $this->railHelpScoutService = $railHelpScoutService;
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle()
    {
        $this->info('Starting SyncHelpScout.');

        $usoraConnection = $this->databaseManager->connection(config('usora.database_connection_name'));
        $railhelpscoutConnection = $this->databaseManager->connection(config('railhelpscout.database_connection_name'));

        $done = 0;
        $total =
            $usoraConnection->table('usora_users')
                ->count();

        $usoraConnection->table('usora_users')
            ->orderBy('id', 'asc')
            ->chunk(
                25,
                function (Collection $userRows) use (&$done, $total, $railhelpscoutConnection, $usoraConnection) {

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
                                $userData->id,
                                $userData->first_name,
                                $userData->last_name,
                                $userData->display_name,
                                $userData->email,
                                $userData->country,
                                $userData->city,
                                $userData->phone_number,
                                $userData->timezone,
                                [$railhelpscoutConnection, $usoraConnection]
                            );
                        }

                        $done++;
                    }

                    $this->info('Done ' . $done . ' out of ' . $total);
                    $this->info('Real: '.(memory_get_peak_usage(true)/1024/1024)." MiB\n\n");

                    $this->ecommerceEntityManager->flush();
                    $this->ecommerceEntityManager->clear();
                    $this->ecommerceEntityManager->getConnection()->ping();
                }
            );
    }

    /**
     * @throws Throwable
     */
    protected function syncUser(
        $usoraId,
        $firstName,
        $lastName,
        $displayName,
        $email,
        $country,
        $city,
        $phoneNumber,
        $timezone,
        $databaseConnections
    ) {

        $attempt = 1;

        $userAttributes =
            $this->helpScoutSyncService->getUsersAttributesById(
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

                $this->railHelpScoutService->createCustomer(
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

                sleep(self::SLEEP_DELAY);
                $attempt++;

                foreach($databaseConnections as $connection) {
                    if (is_null($connection->getPdo())) {
                        $connection->reconnect();
                        $this->info('reconnecting db connection');
                    }
                }

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
}
