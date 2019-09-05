<?php

namespace Railroad\EventDataSynchronizer\Listeners\Intercom;

class PianoteIntercomSyncEventListener extends IntercomSyncEventListenerBase
{
    /*
        Custom product/membership related attributes:
        - pianote_membership_access_expiration_date
        - pianote_membership_status (active, suspended, cancelled)
        - pianote_membership_renewal_date
        - pianote_membership_recurring_type (1 month, 1 year, lifetime)
        - pianote_membership_cancellation_date
        - pianote_membership_start_date
    */

    /*
        Tags:
        - pianote_500_songs_in_5_days_pack_owner
     */

    public function syncUserMembershipAndProductData($userId)
    {
        $userSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($userId);
        $userProducts = $this->userProductRepository->getAllUsersProducts($userId);

        var_dump($userSubscriptions);
        var_dump($userProducts);

        // todo: logic for syncing all
    }
}