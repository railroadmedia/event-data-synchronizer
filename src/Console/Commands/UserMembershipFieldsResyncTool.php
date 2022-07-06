<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(
        DatabaseManager $databaseManager,
        UserMembershipFieldsService $userMembershipFieldsService
    ) {
        $databaseManager->connection(config('ecommerce.database_connection_name'))
            ->disableQueryLog();

        $databaseManager->connection(config('railcontent.database_connection_name'))
            ->disableQueryLog();

        $query = $databaseManager->connection(config('ecommerce.database_connection_name'))
            ->table('ecommerce_user_products')
            ->selectRaw('ecommerce_user_products.user_id as user_id')
            ->join('ecommerce_products', 'ecommerce_products.id', '=', 'ecommerce_user_products.product_id')
            ->where('ecommerce_products.is_physical', false)
            ->groupBy('user_id')
            ->orderBy('user_id', 'asc');

        $this->info('Total to fix: ' . $query->count());

        $query->chunk(500, function (Collection $rows) use ($userMembershipFieldsService) {
            $userIdsToSync = [];

            foreach ($rows as $userProduct) {
                $userIdsToSync[] = $userProduct->user_id;
            }


            foreach ($userIdsToSync as $userInIndex => $userId) {
                $this->info('About to sync: ' . $userId);

                $userMembershipFieldsService->sync($userId);
            }
        });


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