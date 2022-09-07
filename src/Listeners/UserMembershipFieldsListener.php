<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Services\UserMembershipFieldsService;
use Railroad\Resora\Events\Created;
use Railroad\Resora\Events\Updated;

class UserMembershipFieldsListener
{
    /**
     * @var UserMembershipFieldsService
     */
    private $userMembershipFieldsService;

    public function __construct(UserMembershipFieldsService $userMembershipFieldsService)
    {
        $this->userMembershipFieldsService = $userMembershipFieldsService;
    }

    /**
     * @param Created $createdEvent
     */
    public function handleUserProductCreated(UserProductCreated $createdEvent)
    {
        $this->userMembershipFieldsService->sync($createdEvent->getUserProduct()->getUser()->getId());
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleUserProductUpdated(UserProductUpdated $updatedEvent)
    {
        $this->userMembershipFieldsService->sync($updatedEvent->getNewUserProduct()->getUser()->getId());
    }

    /**
     * @param Updated $updatedEvent
     */
    public function handleUserProductDeleted(UserProductDeleted $deletedEvent)
    {
        $this->userMembershipFieldsService->sync($deletedEvent->getUserProduct()->getUser()->getId());
    }
}