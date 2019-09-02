<?php

namespace Railroad\EventDataSynchronizer\Listeners\Maropost;


use Carbon\Carbon;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Maropost\Jobs\SyncContact;
use Railroad\Maropost\ValueObjects\ContactVO;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;

class MaropostEventListener
{

    static $levelActiveToTagMap = [
        'Drumeo Edge' => 'Drumeo - Customer - Member - Active',
    ];

    static $levelExpiredToTagMap = [
        'Drumeo Edge' => 'Drumeo - Customer - Member - ExMember',
    ];


    /**
     * @param OrderPlaced $orderPlaced
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
     * @param UserLevelUpdated $userLevelUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
//        $member = $this->memberDataMapper->get($userLevelUpdated->userId);
//
//        if ($userLevelUpdated->levelExpirationDateTime == null ||
//            $userLevelUpdated->levelExpirationDateTime > Carbon::now()) {
//
//            dispatch(
//                new SyncContact(
//                    new ContactVO(
//                        $member->getEmail(),
//                        $member->getFirstName(),
//                        $member->getLastName(),
//                        ['type' => config('event-data-synchronizer.maropost_contact_type')],
//                        [self::$levelActiveToTagMap[$userLevelUpdated->levelName]],
//                        [self::$levelExpiredToTagMap[$userLevelUpdated->levelName]]
//                    )
//                )
//            );
//        } else {
//
//            dispatch(
//                new SyncContact(
//                    new ContactVO(
//                        $member->getEmail(),
//                        $member->getFirstName(),
//                        $member->getLastName(),
//                        ['type' => config('event-data-synchronizer.maropost_contact_type')],
//                        [self::$levelExpiredToTagMap[$userLevelUpdated->levelName]],
//                        [self::$levelActiveToTagMap[$userLevelUpdated->levelName]]
//                    )
//                )
//            );
//        }
    }

    /**
     * @param UserLevelDeleted $userLevelDeleted
     */
    public function handleUserLevelDeleted(UserLevelDeleted $userLevelDeleted)
    {
//        $member = $this->memberDataMapper->get($userLevelDeleted->userId);
//
//        dispatch(
//            new SyncContact(
//                new ContactVO(
//                    $member->getEmail(),
//                    $member->getFirstName(),
//                    $member->getLastName(),
//                    ['type' => config('event-data-synchronizer.maropost_contact_type')],
//                    [self::$levelExpiredToTagMap[$userLevelDeleted->levelName]],
//                    [self::$levelActiveToTagMap[$userLevelDeleted->levelName]]
//                )
//            )
//        );
    }
}