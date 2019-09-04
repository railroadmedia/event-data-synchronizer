<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Maropost\Jobs\SyncContact;
use Railroad\Maropost\ValueObjects\ContactVO;
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
    protected $description = 'Find all the user products that expired in the last 6 hours and assigns their Maropost tags accordingly.';

    /**
     * @var UserProductRepository
     */
    private $userProductRepository;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * SetLevelTagsForExpiredLevels constructor.
     *
     * @param UserProductRepository $userProductRepository
     * @param UserRepository $userRepository
     */
    public function __construct(UserProductRepository $userProductRepository, UserRepository $userRepository)
    {
        parent::__construct();

        $this->userProductRepository = $userProductRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sixHoursAgo =
            Carbon::now()
                ->subHours(6);

        error_log('6 hours ago: ' . $sixHoursAgo->format('d.m.y - H:i:s'));
        error_log(
            'now: ' .
            Carbon::now()
                ->format('d.m.y - H:i:s')
        );

        $qb = $this->userProductRepository->createQueryBuilder('up');
        $qb->where(
            $qb->expr()
                ->gte('up.expirationDate', ':sixHoursAgo')
        )
            ->andWhere(
                $qb->expr()
                    ->lt('up.expirationDate', ':now')
            )
            ->setParameter('sixHoursAgo', $sixHoursAgo)
            ->setParameter('now', Carbon::now());

        $userProducts =
            $qb->getQuery()
                ->getResult();

        foreach ($userProducts as $userProduct) {
            $user = $userProduct->getUser();
            $product = $userProduct->getProduct();
            $brand = $product->getBrand();

            list($addTags, $removeTags) = $this->getMaropostTags($userProduct, $brand);

            $userDetails = $this->userRepository->find($user->getId());

            dispatch_now(
                new SyncContact(
                    new ContactVO(
                        $userDetails->getEmail(),
                        $userDetails->getFirstName(),
                        $userDetails->getLastName(),
                        ['type' => config('event-data-synchronizer.maropost_contact_type')[$brand]],
                        $addTags,
                        $removeTags
                    )
                )
            );
        }

        $this->info('Updated tags for '.count($userProducts).' expired products.');
        error_log('Updated tags for '.count($userProducts).' expired products.');
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

    /**
     * @param UserProduct $userProduct
     * @param string $brand
     * @return array
     */
    private function getMaropostTags(UserProduct $userProduct, string $brand)
    : array {

        $isMembership = in_array(
            $userProduct->getProduct()
                ->getId(),
            config('event-data-synchronizer.' . $brand . '_membership_product_ids', [])
        );

        $addTags = [];
        $removeTags = [];

        if ($isMembership) {
            $addTags[] = config('event-data-synchronizer.maropost_member_expired_tag')[$brand];
            $removeTags[] = config('event-data-synchronizer.maropost_member_active_tag')[$brand];

        }

        return [$addTags, $removeTags];
    }
}
