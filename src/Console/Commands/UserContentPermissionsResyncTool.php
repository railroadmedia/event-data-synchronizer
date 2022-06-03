<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\Usora\Managers\UsoraEntityManager;
use Railroad\Usora\Repositories\UserRepository;

class UserContentPermissionsResyncTool extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'UserContentPermissionsResyncTool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'UserContentPermissionsResyncTool';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UserContentPermissionsResyncTool {--brand=}';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(
        DatabaseManager $databaseManager,
        UserRepository $userRepository,
        EcommerceEntityManager $ecommerceEntityManager,
        UsoraEntityManager $usoraEntityManager,
        UserProductToUserContentPermissionListener $userProductToUserContentPermissionListener
    ) {
        $brand = $this->option('brand');
        $ecommerceConnection = $databaseManager->connection(config('ecommerce.database_connection_name'));

        $query = $ecommerceConnection->table('ecommerce_user_products')
            ->join('ecommerce_products', 'ecommerce_products.id', '=', 'ecommerce_user_products.product_id')
            ->orderBy('ecommerce_user_products.id', 'desc');

        if (!empty($brand)) {
            $query->where('brand', $brand);
        }

        $done = 0;

        $query->chunk(250, function (Collection $userProductRows) use (
            $usoraEntityManager,
            $ecommerceEntityManager,
            $userProductToUserContentPermissionListener,
            &$done
        ) {
            foreach ($userProductRows as $userProductRow) {
                $userProductToUserContentPermissionListener->syncUserId($userProductRow->user_id);
                $done++;
            }

            $ecommerceEntityManager->flush();
            $usoraEntityManager->flush();

            $ecommerceEntityManager->clear();
            $usoraEntityManager->clear();

            $ecommerceEntityManager->getConnection()->ping();
            $usoraEntityManager->getConnection()->ping();

            $this->info('Done ' . $done);
        });
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
