<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Repositories\UserRepository;

class SyncCustomerIoForUpdatedUserProducts extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SyncCustomerIoForUpdatedUserProducts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all updated user products in the last few days and resync those users since their'.
    'access date may have passed.';

    /**
     * @var UserProductRepository
     */
    private $userProductRepository;

    /**
     * @var CustomerIoSyncEventListener
     */
    private $customerIoSyncEventListener;

    /**
     * @var UserRepository
     */
    private $userRepository;


    /**
     * SetLevelTagsForExpiredLevels constructor.
     *
     * @param  UserProductRepository  $userProductRepository
     * @param  UserRepository  $userRepository
     */
    public function __construct(
        UserProductRepository $userProductRepository,
        CustomerIoSyncEventListener $customerIoSyncEventListener,
        UserRepository $userRepository
    ) {
        parent::__construct();

        $this->userProductRepository = $userProductRepository;
        $this->customerIoSyncEventListener = $customerIoSyncEventListener;
        $this->userRepository = $userRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lastTime = Carbon::now()->subHours(24);

        $qb = $this->userProductRepository->createQueryBuilder('up');

        $qb->where($qb->expr()->gte('up.updatedAt', ':lastDay'))
            ->andWhere($qb->expr()->lt('up.updatedAt', ':now'))
            ->setParameter('lastDay', $lastTime->toDateTimeString())
            ->setParameter('now', Carbon::now()->toDateTimeString());

        /**
         * @var $userProducts UserProduct[]
         */
        $userProducts = $qb->getQuery()->getResult();

        foreach ($userProducts as $userProduct) {
            $user = $this->userRepository->find($userProduct->getUser()->getId());
            $this->customerIoSyncEventListener->handleUserCreated(new UserCreated($user));
        }

        $this->info('Updated customer-io for '.count($userProducts).' updated user products.');

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
