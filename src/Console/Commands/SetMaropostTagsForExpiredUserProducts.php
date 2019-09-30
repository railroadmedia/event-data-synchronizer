<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\EventDataSynchronizer\Listeners\Maropost\MaropostEventListener;
use Railroad\Usora\Repositories\UserRepository;

class SetMaropostTagsForExpiredUserProducts extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'setMaropostTagsForExpiredUserProducts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all the user products that expired in the last day and assigns their Maropost tags accordingly.';

    /**
     * @var UserProductRepository
     */
    private $userProductRepository;

    /**
     * @var MaropostEventListener
     */
    private $maropostEventListener;

    /**
     * SetLevelTagsForExpiredLevels constructor.
     *
     * @param  UserProductRepository  $userProductRepository
     * @param  UserRepository  $userRepository
     */
    public function __construct(
        UserProductRepository $userProductRepository,
        MaropostEventListener $maropostEventListener
    ) {
        parent::__construct();

        $this->userProductRepository = $userProductRepository;
        $this->maropostEventListener = $maropostEventListener;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lastDay = Carbon::now()->subDay();

        $qb = $this->userProductRepository->createQueryBuilder('up');

        $qb->where($qb->expr()->gte('up.expirationDate', ':lastDay'))
            ->andWhere($qb->expr()->lt('up.expirationDate', ':now'))
            ->setParameter('lastDay', $lastDay)
            ->setParameter('now', Carbon::now());

        /**
         * @var $userProducts UserProduct[]
         */
        $userProducts = $qb->getQuery()->getResult();

        foreach ($userProducts as $userProduct) {
            if ($userProduct->getUser()->getId() != 163963) {
                continue;
            }

            $this->info('hit');

            $this->maropostEventListener->syncUser($userProduct->getUser()->getId());
        }

        $this->info('Updated tags for ' . count($userProducts) . ' expired products.');

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
