<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\EventDataSynchronizer\Services\IntercomSyncService;
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
     * @var IntercomSyncService
     */
    private $intercomSyncService;

    /**
     * SetLevelTagsForExpiredLevels constructor.
     * @param  DatabaseManager  $databaseManager
     * @param  UserRepository  $userRepository
     * @param  IntercomSyncService  $intercomSyncService
     */
    public function __construct(
        DatabaseManager $databaseManager,
        UserRepository $userRepository,
        IntercomSyncService $intercomSyncService
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->userRepository = $userRepository;
        $this->intercomSyncService = $intercomSyncService;
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

        $ecommerceConnection->table('usora_users')
            ->orderBy('id', 'asc')
            ->where('email', 'wengel@gmx.net') // todo: remove
            ->chunk(
                500,
                function (Collection $userRows) {
                    $users = $this->userRepository->findByIds(
                        $userRows->pluck('id')
                            ->toArray()
                    );

                    foreach ($users as $user) {
                        // attributes
                        $this->intercomSyncService->syncUsersAttributes($user);

                        // tags
                        $this->intercomSyncService->syncUsersProductOwnershipTags($user);
                    }
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
}
