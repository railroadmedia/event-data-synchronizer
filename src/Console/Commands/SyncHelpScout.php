<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Exception;
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

        $done = 0;
        $total =
            $usoraConnection->table('usora_users')
                ->count();

        $usoraConnection->table('usora_users')
            ->orderBy('id', 'asc')
            // ->where('email', 'bogdan.damian@artsoft-consult.ro') // todo: remove
            ->chunk(
                25,
                function (Collection $userRows) use (&$done, $total) {

                    foreach ($userRows as $userData) {
                        $userAttributes = $this->helpScoutSyncService->getUsersAttributesById(
                            $userData->id,
                            $userData->first_name,
                            $userData->display_name,
                            $userData->country,
                            $userData->city,
                            $userData->phone_number,
                            $userData->timezone
                        );

                        try {
                            $this->railHelpScoutService->createCustomer(
                                $userData->id,
                                $userData->first_name,
                                $userData->last_name,
                                $userData->email,
                                $userAttributes
                            );
                        } catch (Exception $ex) {
                            $this->error(
                                'Exception while processing user with id: '
                                . $userData->id . ', exception message: ' . $ex->getMessage()
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
}
