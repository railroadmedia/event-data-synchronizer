<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use App\ExternalServices\Infusionsoft;
use Carbon\Carbon;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Intercomeo\Jobs\SyncUser;

class InfusionsoftSyncEventListener
{
    /**
     * @var Infusionsoft
     */
    private $infusionsoft;

    const INFUSIONSOFT_TAG_NEW_BUYER_PREFIX = 'NewProductBuyer:';
    const INFUSIONSOFT_TAG_PRODUCT_ACCESS_PREFIX = 'HasAccessToo:';

    /**
     * InfusionsoftSyncEventListener constructor.
     */
    public function __construct(Infusionsoft $infusionsoft)
    {
        $this->infusionsoft = $infusionsoft;
    }

    /**
     * @param OrderEvent $orderEvent
     */
    public function handleOrderEvent(OrderEvent $orderEvent)
    {
        if (empty(
        $orderEvent->getOrder()
            ->getUser()
        )) {
            return;
        }

        $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
            $orderEvent->getOrder()
                ->getUser()
                ->getId()
        );

        $tagIds = [];

        foreach ($orderEvent->getOrder()
                     ->getOrderItems() as $orderItem) {
            $tagIds[] = $this->infusionsoft->syncTag(
                self::INFUSIONSOFT_TAG_NEW_BUYER_PREFIX .
                $orderItem->getProduct()
                    ->getSku()
            );
        }

        $this->infusionsoft->addTagsToContact($infusionsoftContactId, $tagIds);
    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $userProduct = $userProductUpdated->getNewUserProduct();

        if (in_array($userProduct->getId(), config('event-data-synchronizer.pianote_membership_product_ids'))) {
            if ($userProduct->getExpirationDate() == null ||
                Carbon::parse($userProduct->getExpirationDate()) > Carbon::now()) {

                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()
                        ->getId()
                );

                $this->infusionsoft->addTagsToContact(
                    $infusionsoftContactId,
                    [
                        $this->infusionsoft->syncTag(
                            self::INFUSIONSOFT_TAG_PRODUCT_ACCESS_PREFIX .
                            $userProduct->getProduct()
                                ->getSku()
                        )
                    ]
                );
            }
        }

    }

    /**
     * @param UserProductCreated $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $this->handleUserProductUpdated(
            new UserProductUpdated($userProductCreated->getUserProduct(), $userProductCreated->getUserProduct())
        );
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        if (in_array(
            $userProductDeleted->getUserProduct()
                ->getProduct()
                ->getId(),
            config('event-data-synchronizer.pianote_membership_product_ids')
        )) {

            dispatch(
                new SyncUser(
                    $userProductDeleted->getUserProduct()
                        ->getUser()
                        ->getId(), ['custom_attributes' => ['pianote_membership_access_expiration_date' => null,],]
                )
            );

        }
    }
}