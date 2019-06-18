<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\ExternalServices\Infusionsoft;
use Throwable;

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
        try {

            if (empty(
            $orderEvent->getOrder()
                ->getUser()
            )) {
                return;
            }

            $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                $orderEvent->getOrder()
                    ->getUser()
                    ->getEmail()
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

        } catch (Throwable $throwable) {
            error_log($throwable);
            error_log('Error syncing to infusionsoft, API failure');
        }
    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        try {

            $userProduct = $userProductUpdated->getNewUserProduct();

            if (in_array(
                $userProduct->getProduct()
                    ->getId(),
                config('event-data-synchronizer.pianote_membership_product_ids')
            )) {
                if ($userProduct->getExpirationDate() == null ||
                    Carbon::parse($userProduct->getExpirationDate()) > Carbon::now()) {

                    $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                        $userProduct->getUser()
                            ->getEmail()
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

        } catch (Throwable $throwable) {
            error_log($throwable);
            error_log('Error syncing to infusionsoft, API failure');
        }
    }

    /**
     * @param UserProductCreated $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        try {

            $this->handleUserProductUpdated(
                new UserProductUpdated($userProductCreated->getUserProduct(), $userProductCreated->getUserProduct())
            );

        } catch (Throwable $throwable) {
            error_log($throwable);
            error_log('Error syncing to infusionsoft, API failure');
        }
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        try {

            if (in_array(
                $userProductDeleted->getUserProduct()
                    ->getProduct()
                    ->getId(),
                config('event-data-synchronizer.pianote_membership_product_ids')
            )) {

                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProductDeleted->getUserProduct()->getUser()
                        ->getEmail()
                );

                $this->infusionsoft->removeTagsFromContact(
                    $infusionsoftContactId,
                    [
                        $this->infusionsoft->syncTag(
                            self::INFUSIONSOFT_TAG_PRODUCT_ACCESS_PREFIX .
                            $userProductDeleted->getUserProduct()->getProduct()
                                ->getSku()
                        )
                    ]
                );
            }

        } catch (Throwable $throwable) {
            error_log($throwable);
            error_log('Error syncing to infusionsoft, API failure');
        }

    }
}