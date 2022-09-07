<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Entities\Product;

class PackOwnerUserFieldResyncTool extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'PackOwnerUserFieldResyncTool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'PackOwnerUserFieldResyncTool';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PackOwnerUserFieldResyncTool';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(
        DatabaseManager $databaseManager
    ) {
        $databaseManager->connection(config('ecommerce.database_connection_name'))
            ->disableQueryLog();

        $databaseManager->connection(config('railcontent.database_connection_name'))
            ->disableQueryLog();

        $query =
            $databaseManager->connection(config('ecommerce.database_connection_name'))
                ->table('ecommerce_user_products')
                ->selectRaw('ecommerce_user_products.user_id as user_id')
                ->join('ecommerce_products', 'ecommerce_products.id', '=', 'ecommerce_user_products.product_id')
                ->where('ecommerce_products.is_physical', false)
                ->where('ecommerce_products.digital_access_type', Product::DIGITAL_ACCESS_TYPE_SPECIFIC_CONTENT_ACCESS)
                ->where('ecommerce_products.digital_access_time_type', Product::DIGITAL_ACCESS_TIME_TYPE_ONE_TIME)
                ->whereNull('ecommerce_products.deleted_at')
                ->where(function (Builder $builder) {
                    $builder->where(
                        'ecommerce_user_products.expiration_date',
                        '>',
                        Carbon::now()
                            ->toDateTimeString()
                    )
                        ->orWhereNull('ecommerce_user_products.expiration_date');
                })
                ->groupBy('user_id')
                ->orderBy('user_id', 'asc');

        $count = 0;
        $query->chunk(500, function (Collection $rows) use ($databaseManager, &$count) {
            $userIdsToSync = [];

            foreach ($rows as $userProduct) {
                $userIdsToSync[] = $userProduct->user_id;
            }
            $databaseManager->connection(config('ecommerce.database_connection_name'))
                ->table('usora_users')
                ->whereIn('id', $userIdsToSync)
                ->update(
                    [
                        'is_pack_owner' => true,
                    ]
                );

            $count = $count + count($userIdsToSync);

            $this->info('Sync pack owner users : '.$count);
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
