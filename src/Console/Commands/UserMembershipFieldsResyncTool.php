<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Railroad\EventDataSynchronizer\Services\UserMembershipFieldsService;

class UserMembershipFieldsResyncTool extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'UserMembershipFieldsResyncTool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'UserMembershipFieldsResyncTool';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UserMembershipFieldsResyncTool';

    private DatabaseManager $databaseManager;
    private UserMembershipFieldsService $userMembershipFieldsService;

    /**
     * UserMembershipFieldsResyncTool constructor.
     * @param DatabaseManager $databaseManager
     */
    public function __construct(
        DatabaseManager $databaseManager,
        UserMembershipFieldsService $userMembershipFieldsService
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->userMembershipFieldsService = $userMembershipFieldsService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->databaseManager->connection(config('ecommerce.database_connection_name'))
            ->disableQueryLog();

        $this->databaseManager->connection(config('railcontent.database_connection_name'))
            ->disableQueryLog();

        $userProducts = $this->databaseManager->connection(config('ecommerce.database_connection_name'))
            ->table('ecommerce_user_products')
            ->join('ecommerce_products', 'ecommerce_products.id', '=', 'ecommerce_user_products.product_id')
            ->where('ecommerce_products.is_physical', false)
            ->orderBy('ecommerce_user_products.id', 'asc')
            ->get();

        $userIdsToSync = [];

        foreach ($userProducts as $userProduct) {
            $userIdsToSync[] = $userProduct->user_id;
        }

        $this->info('Total to fix: ' . count($userIdsToSync));

        foreach ($userIdsToSync as $userInIndex => $userId) {
            $this->info('About to sync: ' . $userId);

            $this->userMembershipFieldsService->sync($userId);
        }

        return true;
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
