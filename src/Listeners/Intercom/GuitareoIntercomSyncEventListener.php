<?php

namespace Railroad\EventDataSynchronizer\Listeners\Intercom;

class GuitareoIntercomSyncEventListener extends IntercomSyncEventListenerBase
{
    /*
    Custom product/membership related attributes:
    - guitareo_membership_access_expiration_date
    - guitareo_membership_status (active, suspended, cancelled)
    - guitareo_membership_renewal_date
    - guitareo_membership_recurring_type (1 month, 1 year, lifetime)
    - guitareo_membership_cancellation_date
    - guitareo_membership_start_date
*/

    /*
        Tags:
        - guitareo_pack_owner // todo
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