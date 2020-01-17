<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\EventDataSynchronizer\Listeners\UserProductToUserContentPermissionListener;
use Railroad\EventDataSynchronizer\Services\IntercomSyncServiceBase;
use Railroad\Intercomeo\Services\IntercomeoService;
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
     * @var EcommerceEntityManager
     */
    private $ecommerceEntityManager;

    /**
     * @var UsoraEntityManager
     */
    private $usoraEntityManager;
    /**
     * @var UserProductToUserContentPermissionListener
     */
    private $userProductToUserContentPermissionListener;

    /**
     * SetLevelTagsForExpiredLevels constructor.
     * @param DatabaseManager $databaseManager
     * @param UserRepository $userRepository
     * @param IntercomSyncServiceBase $intercomSyncService
     * @param IntercomeoService $intercomeoService
     * @param EcommerceEntityManager $ecommerceEntityManager
     * @param UsoraEntityManager $usoraEntityManager
     * @param UserProductToUserContentPermissionListener $userProductToUserContentPermissionListener
     */
    public function __construct(
        DatabaseManager $databaseManager,
        UserRepository $userRepository,
        IntercomSyncServiceBase $intercomSyncService,
        IntercomeoService $intercomeoService,
        EcommerceEntityManager $ecommerceEntityManager,
        UsoraEntityManager $usoraEntityManager,
        UserProductToUserContentPermissionListener $userProductToUserContentPermissionListener
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->userRepository = $userRepository;
        $this->intercomSyncService = $intercomSyncService;
        $this->intercomeoService = $intercomeoService;
        $this->ecommerceEntityManager = $ecommerceEntityManager;
        $this->usoraEntityManager = $usoraEntityManager;
        $this->userProductToUserContentPermissionListener = $userProductToUserContentPermissionListener;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $brand = $this->option('brand');
        $ecommerceConnection = $this->databaseManager->connection(config('ecommerce.database_connection_name'));

        $query = $ecommerceConnection->table('ecommerce_user_products')
            ->join('ecommerce_products', 'ecommerce_products.id', '=', 'ecommerce_user_products.product_id')
            ->orderBy('ecommerce_user_products.id', 'desc');

        if (!empty($brand)) {
            $query->where('brand', $brand);
        }

        $done = 0;

        $query->chunk(500, function (Collection $userProductRows) use (&$done) {
            foreach ($userProductRows as $userProductRow) {
                $this->userProductToUserContentPermissionListener->syncUserId($userProductRow->user_id);
                $done++;
            }

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
