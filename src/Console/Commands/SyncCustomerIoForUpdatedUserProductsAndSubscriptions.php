<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Railroad\Ecommerce\Entities\Subscription;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;

class SyncCustomerIoForUpdatedUserProductsAndSubscriptions extends Command
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
    protected $description = 'Find all updated user products and expired subs in the last few days and '.
    'resync those users since their access date may have passed.';

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
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;


    /**
     * SetLevelTagsForExpiredLevels constructor.
     *
     * @param  UserProductRepository  $userProductRepository
     * @param  UserRepository  $userRepository
     */
    public function __construct(
        UserProductRepository $userProductRepository,
        CustomerIoSyncEventListener $customerIoSyncEventListener,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository
    ) {
        parent::__construct();

        $this->userProductRepository = $userProductRepository;
        $this->customerIoSyncEventListener = $customerIoSyncEventListener;
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lastTime = Carbon::now()->subHours(48);

        $qb = $this->userProductRepository->createQueryBuilder('up');

        $qb->where($qb->expr()->gte('up.updatedAt', ':lastDay'))
            ->andWhere($qb->expr()->lt('up.updatedAt', ':now'))
            ->setParameter('lastDay', $lastTime->toDateTimeString())
            ->setParameter('now', Carbon::now()->toDateTimeString());

        /**
         * @var $userProducts UserProduct[]
         */
        $userProducts = $qb->getQuery()->getResult();

        $qb = $this->subscriptionRepository->createQueryBuilder('s');

        $qb->orWhere('s.paidUntil > :lastDay AND s.paidUntil < :now')
            ->orWhere('s.updatedAt > :lastDay AND s.updatedAt < :now')
            ->orWhere('s.canceledOn > :lastDay AND s.canceledOn < :now')
            ->setParameter('lastDay', $lastTime->toDateTimeString())
            ->setParameter('now', Carbon::now()->toDateTimeString());

        /**
         * @var $subscriptions Subscription[]
         */
        $subscriptions = $qb->getQuery()->getResult();

        $allUserIds = [];

        foreach ($userProducts as $userProduct) {
            $allUserIds[] = $userProduct->getUser()->getId();
        }

        foreach ($subscriptions as $subscription) {
            $allUserIds[] = $subscription->getUser()->getId();
        }

        $allUserIds = array_unique($allUserIds);

        foreach ($allUserIds as $allUserId) {
            $user = $this->userRepository->find($allUserId);
            $this->info('Handling ' . $user->getEmail());
            $this->customerIoSyncEventListener->handleUserUpdated(new UserUpdated($user, $user));
        }

        $this->info('Updated customer-io for '.count($allUserIds).' user IDs.');

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
