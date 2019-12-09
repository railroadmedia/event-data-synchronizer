# Pianote Event List/Summary

## Things That Need to Happen Cross Application

1. Intercom sync
    - user details
    - payment method expiration date (active)
    - user access expiration date
    - subscription details
    
## Event/Data List
    
## Intercom

- on user C/U/D
    - email
    - created_at
    - name
    - avatar
    - pianote_user
    - pianote_display_name
    - pianote_birthday
    
- on payment method C/U/D or subscription C/U/D
    - pianote_primary_payment_method_expiration_date
    
- on product access C/U/D
    - pianote_membership_access_expiration_date
    - pianote_is_lifetime_member
    - pianote_membership_access_expiration_date
    
- on subscription C/U/D
    - pianote_membership_status (active, suspended, canceled)
    - pianote_membership_type (interval_count . '_' . interval_type)
    - pianote_membership_renewal_date
    - pianote_primary_payment_method_expiration_date
    - pianote_membership_cancellation_date
    - pianote_membership_started_date