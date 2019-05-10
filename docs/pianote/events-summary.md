# Pianote Event List/Summary

## Things That Need to Happen Cross Application

1. Infusionsoft sync
    - purchased order items (NewProductBuyer:SKU)
    - subscription status (ActiveSub:SKU, ExSub:SKU)
    - contact email on email change
2. Intercom sync
    - user details
    - payment method expiration date (active)
    - user access expiration date
    - subscription details
    
## Event/Data List

## Infusionsoft

<!--- on subscription C/U/D-->
<!--    - if sub is active add tag: ActiveSub:_SKU_-->
<!--    - if sub is active remove tag: ExSub:_SKU_-->
<!--    - if sub is not active, canceled, or deleted, add tag: ExSub:_SKU_ -->
<!--    - if sub is not active, canceled, or deleted, remove tag: ActiveSub:_SKU_ -->
- on new order placed
    - for each new product purchased, add tag: NewProductBuyer:_SKU_
- on product access C/U/D:
    - if has product access: HasAccessToo:_SKU_
    
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
    - pianote_membership_subscription_status (active, suspended, canceled)
    - pianote_membership_subscription_type (interval_count . '_' . interval_type)
    - pianote_membership_subscription_renewal_date
    - pianote_primary_payment_method_expiration_date
    - pianote_membership_subscription_cancellation_date
    - pianote_membership_subscription_started_date