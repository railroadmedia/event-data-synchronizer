<?php

namespace Railroad\EventDataSynchronizer\Listeners\Maropost;


use Carbon\Carbon;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Maropost\Jobs\SyncContact;
use Railroad\Maropost\ValueObjects\ContactVO;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;

class MaropostEventListener
{
    /**
     * @param OrderEvent $orderEvent
     */
    public function handleOrderPlaced(OrderEvent $orderEvent)
    {
        if (empty(
        $orderEvent->getOrder()
            ->getUser()
        )) {
            return;
        }

        $maropostTags = [];
        foreach ( $orderEvent->getOrder()->getOrderItems() as $item) {
            if (config('product_sku_maropost_tag_mapping')[$item->getProduct()
                ->getSku()]) {
                $maropostTags[] =
                    config('product_sku_maropost_tag_mapping')[$item->getProduct()
                        ->getSku()];
            }
        }
        error_log('user::::::::::::::::::::::::::::::::::'.print_r($orderEvent->getOrder()
                ->getUser(), true));

        error_log(
            'Sync Maropost contact with email ' . $orderEvent->getOrder()
                ->getUser()->getEmail() . ', add tags ::' . print_r($maropostTags, true)
        );

        //maropost sync
        dispatch(
        new SyncContact(
            new ContactVO(
                $orderEvent->getOrder()
                    ->getUser()->getEmail(),
                '',
                '',
                ['type' => config('event-data-synchronizer.maropost_contact_type')],
                $maropostTags
            )
        )
    );

    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $userProduct = $userProductUpdated->getNewUserProduct();

        if (in_array(
            $userProduct->getProduct()
                ->getId(),
            config('event-data-synchronizer.pianote_membership_product_ids')
        )) {

            // product access tag
            if ($userProduct->getExpirationDate() == null ||
                Carbon::parse($userProduct->getExpirationDate()) > Carbon::now()) {
                dispatch(
                    new SyncContact(
                        new ContactVO(
                            $userProduct->getUser()->getEmail(),
                            '',
                            '',
                            ['type' => config('event-data-synchronizer.maropost_contact_type')],
                            [ config('product_sku_maropost_tag_mapping')[$userProduct->getProduct()->getSku()]]
                        )
                    )
                );
            } else {
                dispatch(
                    new SyncContact(
                        new ContactVO(
                            $userProduct->getUser()->getEmail(),
                            '',
                            '',
                            ['type' => config('event-data-synchronizer.maropost_contact_type')],
                            [],
                            [ config('product_sku_maropost_tag_mapping')[$userProduct->getProduct()->getSku()]]
                        )
                    )
                );
            }
        }
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserLevelDeleted(UserProductDeleted $userProductDeleted)
    {

    }
}