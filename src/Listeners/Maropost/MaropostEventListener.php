<?php

namespace Railroad\EventDataSynchronizer\Listeners\Maropost;


use Carbon\Carbon;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Maropost\Jobs\SyncContact;
use Railroad\Maropost\ValueObjects\ContactVO;
use Railroad\Usora\Repositories\UserRepository;

class MaropostEventListener
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * MaropostEventListener constructor.
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $userProduct = $userProductUpdated->getNewUserProduct();

        $user = $this->userRepository->find(
            $userProduct->getUser()
                ->getId()
        );

        $brand =
            $userProduct->getProduct()
                ->getBrand();

        list($addTags, $removeTags) = $this->getMaropostTags($userProduct, $brand);

        dispatch(
            new SyncContact(
                new ContactVO(
                    $user->getEmail(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    ['type' => config('event-data-synchronizer.maropost_contact_type')[$brand]],
                    $addTags,
                    $removeTags
                )
            )
        );
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        $userProduct = $userProductDeleted->getUserProduct();

        $user = $this->userRepository->find(
            $userProduct->getUser()
                ->getId()
        );

        $brand =
            $userProduct->getProduct()
                ->getBrand();

        $isMembership = in_array(
            $userProduct->getProduct()
                ->getId(),
            config('event-data-synchronizer.' . $brand . '_membership_product_ids',[])
        );

        if ($isMembership) {
            dispatch(
                new SyncContact(
                    new ContactVO(

                        $user->getEmail(),
                        $user->getFirstName(),
                        $user->getLastName(),
                        ['type' => config('event-data-synchronizer.maropost_contact_type')[$brand]],
                        [config('event-data-synchronizer.maropost_member_expired_tag')[$brand]],
                        [config('event-data-synchronizer.maropost_member_active_tag')[$brand]]
                    )
                )
            );
        }
    }

    /**
     * @param UserProductCreated $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $userProduct = $userProductCreated->getUserProduct();

        $user = $this->userRepository->find(
            $userProduct->getUser()
                ->getId()
        );

        $brand =
            $userProduct->getProduct()
                ->getBrand();

        list($addTags, $removeTags) = $this->getMaropostTags($userProduct, $brand);

        dispatch(
            new SyncContact(
                new ContactVO(
                    $user->getEmail(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    ['type' => config('event-data-synchronizer.maropost_contact_type')[$brand]],
                    $addTags,
                    $removeTags
                )
            )
        );
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
            config('event-data-synchronizer.' . $brand . '_membership_product_ids',[])
        );

        $addTags = [];
        $removeTags = [];

        if ($isMembership) {
            if ($userProduct->getExpirationDate() == null ||
                Carbon::parse($userProduct->getExpirationDate()) > Carbon::now()) {
                $addTags[] = config('event-data-synchronizer.maropost_member_active_tag')[$brand];
                $removeTags[] = config('event-data-synchronizer.maropost_member_expired_tag')[$brand];
            } else {
                $addTags[] = config('event-data-synchronizer.maropost_member_expired_tag')[$brand];
                $removeTags[] = config('event-data-synchronizer.maropost_member_active_tag')[$brand];
            }
        } else {
            $addTags[] = config(
                'product_sku_maropost_tag_mapping.' .
                $userProduct->getProduct()
                    ->getSku()
            );
        }
        return [$addTags, $removeTags];
    }
}