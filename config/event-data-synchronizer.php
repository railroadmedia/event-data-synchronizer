<?php

return [
    // brands membership product skus
    'brand_membership_product_skus' => ['MY-SKU1'],
    'otherBrand_membership_product_skus' => ['MY-SKU2'],

    // user
    'users_database_connection_name' => 'testbench',
    'users_table_name' => 'usora_users',

    // ecommerce
    'ecommerce_product_sku_to_content_permission_name_map' => [
        'SKU-1' => 'My Permission 1',
        'SKU-2' => 'My Permission 2',
    ],

    'help_scout_sync_brands' => ['drumeo', 'pianote', 'singeo', 'guitareo'],

    'helpscout_queue_connection_name' => 'database',
    'helpscout_queue_name' => 'helpscout',

    // impact railanalytics
    'impact_queue_connection_name' => 'database',
    'impact_queue_name' => 'impact',

    // customer.io
    // NOTE: you must add this queue to your queue worker setup, ex: artisan queue:work database --queue=customer_io
    'customer_io_queue_connection_name' => 'database',
    'customer_io_queue_name' => 'customer-io',

    'customer_io_brands_to_sync' => ['drumeo', 'pianote', 'guitareo'],

    // all brands will always be synced to this workspace regardless of their products or interaction
    'customer_io_account_to_sync_all_brands' => 'musora',

    // only the brands set for a given customer.io account will have their info synced
    // see customer-io.php accounts config value
    'customer_io_account_name_brands_to_sync' => [
        'account_1' => ['drumeo', 'pianote'],
        'account_2' => ['musora'],
        'account_3' => ['another_brand'],
    ],

    'customer_io_brand_activity_event' => 'drumeo',

    // sasquatch integration
    'customer_io_saasquatch_email_invite_event_name' => '_saasquatch_referral-link_30-day',
    'customer_io_saasquatch_email_invite_link_attribute_name' => '_saasquatch_referral-link_30-day',

    // pack ownership syncing, all packs should be listed here that will go in the pack ownership attribute
    'customer_io_pack_skus_to_sync_ownership' => ['my-pack-1', 'my-pack-2'],

    // if the users subscription is a trial, the attribute 'brand_membership_subscription_trial-type' will be set based on this
    'customer_io_trial_product_sku_to_type' => [
        'brand' => [
            'sku' => 'string_type',
            'DLM-Trial-30-Day' => '1_month_free',

            // confusing name, we changed this product to 7 days free but did not update the sku
            'DLM-Trial-1-month' => '7_days_free',
        ],
    ],

    'customer_io_pack_sku_to_purchase_event_name' => [
        'pack-sku' => 'event_name'
    ],

    'customer_io_content_type_to_event_string_map' => [
        'course' => 'course',
        'learning-path' => 'learning-path',
        'learning-path-course' => 'learning-path_course',
        'learning-path-lesson' => 'learning-path_lesson',
        'unit' => 'learning-path',
        'play-along' => 'play-along',
        'song' => 'song',
        'pack' => 'pack',
        'semester-pack' => 'pack',
        'semester-pack-lesson' => 'pack',
        'pack-bundle-lesson' => 'pack-bundle-lesson',
        'coach-stream' => 'coach_lesson',
        'rudiment' => 'rudiment',
        'show' => 'show',
        '25-days-of-christmas' => 'show',
        'behind-the-scenes' => 'show',
        'boot-camps' => 'show',
        'camp-drumeo-ah' => 'show',
        'challenges' => 'show',
        'diy-drum-experiments' => 'show',
        'exploring-beats' => 'show',
        'gear-guides' => 'show',
        'ha-oemurd-pmac' => 'show',
        'in-rhythm' => 'show',
        'live' => 'show',
        'namm-2019' => 'show',
        'on-the-road' => 'show',
        'paiste-cymbals' => 'show',
        'performances' => 'show',
        'podcasts' => 'show',
        'question-and-answer' => 'show',
        'quick-tips' => 'show',
        'recording' => 'show',
        'rhythmic-adventures-of-captain-carson' => 'show',
        'rhythms-from-another-planet' => 'show',
        'solos' => 'show',
        'sonor-drums' => 'show',
        'student-collaborations' => 'show',
        'student-focus' => 'show',
        'student-review' => 'show',
        'study-the-greats' => 'show',
        'tama-drums' => 'show',
    ],
];