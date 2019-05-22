<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\Ecommerce\Repositories\ProductRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Railcontent\Repositories\PermissionRepository;
use Railroad\Railcontent\Repositories\UserPermissionsRepository;
use Railroad\Railcontent\Services\ConfigService;
use Railroad\Resora\Entities\Entity;
use Railroad\Resora\Events\Created;
use Railroad\Resora\Events\Updated;

class DuplicateSubscriptionHandler
{
    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;
    /**
     * @var EcommerceEntityManager
     */
    private $ecommerceEntityManager;

    /**
     * DuplicateSubscriptionHandler constructor.
     * @param SubscriptionRepository $subscriptionRepository
     * @param EcommerceEntityManager $ecommerceEntityManager
     */
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        EcommerceEntityManager $ecommerceEntityManager
    )
    {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->ecommerceEntityManager = $ecommerceEntityManager;
    }

    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        $this->handleSubscriptionUpdated(
            new SubscriptionUpdated($subscriptionCreated->getSubscription(), $subscriptionCreated->getSubscription())
        );

        // if the user already had time in their user_product row, the renewal date should be extended to account
        // for the existing time
    }

    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        $subscription = $subscriptionUpdated->getNewSubscription();

        if (in_array(
                $subscription->getProduct()
                    ->getId(),
                config('event-data-synchronizer.pianote_membership_product_ids')
            ) && $subscription->getIsActive() && empty($subscription->getCanceledOn())) {

            $allUserSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions(
                $subscription->getUser()
                    ->getId(),
                config('event-data-synchronizer.pianote_membership_product_ids')
            );

            foreach ($allUserSubscriptions as $otherSubscription) {

                if (in_array(
                        $otherSubscription->getProduct()
                            ->getId(),
                        config('event-data-synchronizer.pianote_membership_product_ids')
                    ) &&
                    $otherSubscription->getIsActive() &&
                    empty($otherSubscription->getCanceledOn()) &&
                    $otherSubscription->getId() != $subscription->getId()) {

                    $otherSubscription->setIsActive(false);

                    $this->ecommerceEntityManager->persist($otherSubscription);
                    $this->ecommerceEntityManager->flush();
                }
            }
        }
    }
}