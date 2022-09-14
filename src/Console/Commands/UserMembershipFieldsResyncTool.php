<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
    protected $signature = 'UserMembershipFieldsResyncTool {startingUserId=0} {endingUserId=9999999}';

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

        if (!empty($this->argument('startingUserId'))) {
            $query->where('user_id', '>', $this->argument('startingUserId'));
        }

        if (!empty($this->argument('endingUserId'))) {
            $query->where('user_id', '<', $this->argument('endingUserId'));
        }

        $this->info('Total to fix around 232k');

        $totalSynced = 0;

        $query->chunk(250, function (Collection $rows) use ($userMembershipFieldsService, &$totalSynced) {
            $userIdsToSync = [];

            foreach ($rows as $userProduct) {
                $userIdsToSync[] = $userProduct->user_id;
            }


            $userMembershipFieldsService->syncUserIds($userIdsToSync);
            $totalSynced += count($userIdsToSync);

            $this->info($totalSynced . ' done. Last processed user id: ' . $userProduct->user_id);
            Log::info($totalSynced . ' done. Last processed user id: ' . $userProduct->user_id);
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
