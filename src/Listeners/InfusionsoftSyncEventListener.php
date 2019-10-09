<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Services\UserProductService;
use Railroad\EventDataSynchronizer\ExternalServices\Infusionsoft;
use Throwable;

class InfusionsoftSyncEventListener
{
    /**
     * @var Infusionsoft
     */
    private $infusionsoft;

    /**
     * @var UserProductService
     */
    private $userProductService;

    const INFUSIONSOFT_TAG_NEW_BUYER_PREFIX = 'NewProductBuyer:';
    const INFUSIONSOFT_TAG_PRODUCT_ACCESS_PREFIX = 'HasAccessToo:';
    const INFUSIONSOFT_TAG_IS_MEMBER = 'Pianote Member';
    const INFUSIONSOFT_TAG_GUITAREO_IS_MEMBER = 'Guitareo';
    const INFUSIONSOFT_TAG_500_SONGS = '500-Songs-Pack-Owner';
    const INFUSIONSOFT_TAG_AGME_JAN_2019_SEMESTER = 'AGME - Pack Owner';
    const INFUSIONSOFT_TAG_GUITAREO_TRIAL = 'Trigger - Active Trial';

    /**
     * InfusionsoftSyncEventListener constructor.
     * @param  Infusionsoft  $infusionsoft
     * @param  UserProductService  $userProductService
     */
    public function __construct(Infusionsoft $infusionsoft, UserProductService $userProductService)
    {
        $this->infusionsoft = $infusionsoft;
        $this->userProductService = $userProductService;
    }

    /**
     * @param  OrderEvent  $orderEvent
     */
    public function handleOrderEvent(OrderEvent $orderEvent)
    {
        try {

            if (empty(
            $orderEvent->getOrder()->getUser()
            )) {
                return;
            }

            $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                $orderEvent->getOrder()->getUser()->getEmail()
            );

            $tagIds = [];

            foreach ($orderEvent->getOrder()->getOrderItems() as $orderItem) {
                $tagIds[] = $this->infusionsoft->syncTag(
                    self::INFUSIONSOFT_TAG_NEW_BUYER_PREFIX . $orderItem->getProduct()->getSku()
                );
            }

            if (!empty($tagIds)) {
                $this->infusionsoft->addTagsToContact($infusionsoftContactId, $tagIds);
            }

        } catch (Throwable $throwable) {
            error_log($throwable);
            error_log('Error syncing to infusionsoft, API failure');
        }
    }

    /**
     * @param  UserProductUpdated  $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        try {

            $userProduct = $userProductUpdated->getNewUserProduct();

            // pianote
            if (in_array(
                    $userProduct->getProduct()->getId(),
                    config('event-data-synchronizer.pianote_membership_product_ids')
                ) || $userProduct->getProduct()->getBrand() == 'guitareo') {
                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()->getEmail()
                );

                // product access tag
                if ($userProduct->getExpirationDate() == null ||
                    Carbon::parse($userProduct->getExpirationDate()) > Carbon::now()) {

                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_PRODUCT_ACCESS_PREFIX . $userProduct->getProduct()->getSku()
                            )
                        ]
                    );
                }

                // is member tag
                if ($this->userProductService->hasAnyOfProducts(
                    $userProduct->getUser()->getId(),
                    config('event-data-synchronizer.pianote_membership_product_ids')
                )) {
                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_IS_MEMBER
                            )
                        ]
                    );
                } else {
                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_IS_MEMBER
                            )
                        ]
                    );
                }
            }

            // 500 songs
            if ($userProductUpdated->getNewUserProduct()->getProduct()->getSku() == '500-songs-in-5-days' ||
                $userProductUpdated->getNewUserProduct()->getProduct()->getSku() == '500-songs-in-5-days-99') {

                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()->getEmail()
                );

                if ($userProductUpdated->getNewUserProduct()->getExpirationDate() == null ||
                    $userProductUpdated->getNewUserProduct()->getExpirationDate() > Carbon::now()) {

                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_500_SONGS
                            )
                        ]
                    );

                } else {
                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_500_SONGS
                            )
                        ]
                    );
                }
            }

            // agme jan semester
            if ($userProductUpdated->getNewUserProduct()->getProduct()->getSku() == 'AGME-JAN-2019-SEMESTER') {

                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()->getEmail()
                );

                if ($userProductUpdated->getNewUserProduct()->getExpirationDate() == null ||
                    $userProductUpdated->getNewUserProduct()->getExpirationDate() > Carbon::now()) {

                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_AGME_JAN_2019_SEMESTER
                            )
                        ]
                    );

                } else {
                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_AGME_JAN_2019_SEMESTER
                            )
                        ]
                    );
                }
            }

            // guitareo trial
            if ($userProductUpdated->getNewUserProduct()->getProduct()->getSku() == 'GUITAREO-7-DAY-TRIAL-ONE-TIME') {

                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()->getEmail()
                );

                if ($userProductUpdated->getNewUserProduct()->getExpirationDate() == null ||
                    $userProductUpdated->getNewUserProduct()->getExpirationDate() > Carbon::now()) {

                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_GUITAREO_TRIAL
                            )
                        ]
                    );

                } else {
                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_GUITAREO_TRIAL
                            )
                        ]
                    );
                }
            }

            // guitareo membership tag
            if ($this->userProductService->hasAnyOfProducts(
                $userProduct->getUser()->getId(),
                config('event-data-synchronizer.guitareo_membership_product_ids')
            )) {
                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()->getEmail()
                );

                $this->infusionsoft->addTagsToContact(
                    $infusionsoftContactId,
                    [
                        $this->infusionsoft->syncTag(
                            self::INFUSIONSOFT_TAG_GUITAREO_IS_MEMBER
                        )
                    ]
                );
            } else {
                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProduct->getUser()->getEmail()
                );

                $this->infusionsoft->removeTagsFromContact(
                    $infusionsoftContactId,
                    [
                        $this->infusionsoft->syncTag(
                            self::INFUSIONSOFT_TAG_GUITAREO_IS_MEMBER
                        )
                    ]
                );
            }

        } catch (Throwable $throwable) {
            error_log($throwable);
            error_log('Error syncing to infusionsoft, API failure');
        }
    }

    /**
     * @param  UserProductCreated  $userProductCreated
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
     * @param  UserProductDeleted  $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        try {

            if (in_array(
                    $userProductDeleted->getUserProduct()->getProduct()->getId(),
                    config('event-data-synchronizer.pianote_membership_product_ids')
                ) || $userProductDeleted->getUserProduct()->getProduct()->getBrand() == 'guitareo') {

                $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                    $userProductDeleted->getUserProduct()->getUser()->getEmail()
                );

                $this->infusionsoft->removeTagsFromContact(
                    $infusionsoftContactId,
                    [
                        $this->infusionsoft->syncTag(
                            self::INFUSIONSOFT_TAG_PRODUCT_ACCESS_PREFIX .
                            $userProductDeleted->getUserProduct()->getProduct()->getSku()
                        )
                    ]
                );

                // is member tag
                if ($this->userProductService->hasAnyOfProducts(
                    $userProductDeleted->getUserProduct()->getUser()->getId(),
                    config('event-data-synchronizer.pianote_membership_product_ids')
                )) {
                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_IS_MEMBER
                            )
                        ]
                    );
                } else {
                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_IS_MEMBER
                            )
                        ]
                    );
                }

                // 500 songs
                if ($userProductDeleted->getUserProduct()->getProduct()->getSku() == '500-songs-in-5-days') {

                    $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                        $userProductDeleted->getUserProduct()->getUser()->getEmail()
                    );

                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_500_SONGS
                            )
                        ]
                    );

                }

                // agme jan semester
                if ($userProductDeleted->getUserProduct()->getProduct()->getSku() == 'AGME-JAN-2019-SEMESTER') {

                    $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                        $userProductDeleted->getUserProduct()->getUser()->getEmail()
                    );

                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_AGME_JAN_2019_SEMESTER
                            )
                        ]
                    );
                }

                // guitareo trial
                if ($userProductDeleted->getUserProduct()->getProduct()->getSku() == 'GUITAREO-7-DAY-TRIAL-ONE-TIME') {

                    $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                        $userProductDeleted->getUserProduct()->getUser()->getEmail()
                    );

                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_GUITAREO_TRIAL
                            )
                        ]
                    );
                }

                // is guitareo tag
                if ($this->userProductService->hasAnyOfProducts(
                    $userProductDeleted->getUserProduct()->getUser()->getId(),
                    config('event-data-synchronizer.guitareo_membership_product_ids')
                )) {
                    $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                        $userProductDeleted->getUserProduct()->getUser()->getEmail()
                    );

                    $this->infusionsoft->addTagsToContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_GUITAREO_IS_MEMBER
                            )
                        ]
                    );
                } else {
                    $infusionsoftContactId = $this->infusionsoft->syncContactsForEmailOnly(
                        $userProductDeleted->getUserProduct()->getUser()->getEmail()
                    );

                    $this->infusionsoft->removeTagsFromContact(
                        $infusionsoftContactId,
                        [
                            $this->infusionsoft->syncTag(
                                self::INFUSIONSOFT_TAG_GUITAREO_IS_MEMBER
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
}