<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Railroad\EventDataSynchronizer\Services\IntercomSyncServiceBase;
use Railroad\Intercomeo\Services\IntercomeoService;
use Railroad\Usora\Repositories\UserRepository;

class IntercomReSyncTool extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'IntercomResyncService';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync users and customers to intercom.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'IntercomResyncService {scope} {brand?}';

    /**
     * @var DatabaseManager
     */
    private $databaseManager;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var IntercomSyncServiceBase
     */
    private $intercomSyncService;

    /**
     * @var IntercomeoService
     */
    private $intercomeoService;

    /**
     * SetLevelTagsForExpiredLevels constructor.
     * @param  DatabaseManager  $databaseManager
     * @param  UserRepository  $userRepository
     * @param  IntercomSyncServiceBase  $intercomSyncService
     * @param  IntercomeoService  $intercomeoService
     */
    public function __construct(
        DatabaseManager $databaseManager,
        UserRepository $userRepository,
        IntercomSyncServiceBase $intercomSyncService,
        IntercomeoService $intercomeoService
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->userRepository = $userRepository;
        $this->intercomSyncService = $intercomSyncService;
        $this->intercomeoService = $intercomeoService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->argument('scope') == 'all') {
            $this->all($this->argument('brand'));
        }
    }

    /**
     * @param  null  $brand
     */
    protected function all($brand = null)
    {
        $ecommerceConnection = $this->databaseManager->connection(config('ecommerce.database_connection_name'));

        $done = 0;
        $total =
            $ecommerceConnection->table('usora_users')
                ->count();

        $ecommerceConnection->table('usora_users')
            ->orderBy('id', 'desc')
            //            ->where('email', 'wengel@gmx.net') // todo: remove
            ->chunk(
                25,
                function (Collection $userRows) use (&$done, $total) {
                    $users = $this->userRepository->findByIds(
                        $userRows->pluck('id')
                            ->toArray()
                    );

                    foreach ($users as $user) {
                        // attributes
                        $this->intercomSyncService->syncUsersAttributes($user);

                        // tags
                        $this->intercomSyncService->syncUsersProductOwnershipTags($user);

                        $done++;

                        if (((integer)$this->intercomeoService->getRateLimitDetails()['remaining'] ?? 50) < 10) {
                            $this->info('Waiting for API.');
                            sleep(10);
                        }

                        $this->info('Starting ' . $user->getEmail());

                        $this->info(
                            'Done ' .
                            $done .
                            ' out of ' .
                            $total .
                            ' - ' .
                            ($this->intercomeoService->getRateLimitDetails()['remaining'] ?? 0) .
                            ' requests left for intercom API limit.');
                    }

                    $this->info('Done ' . $done . ' out of ' . $total);
                }
            );
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

    public function info($string, $verbosity = null)
    {
        Log::info($string);

        parent::info($string, $verbosity);
    }

}
