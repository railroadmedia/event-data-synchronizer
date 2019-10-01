<?php

return [
    'brand' => 'musora',

    'ecommerce_product_sku_to_content_permission_name_map' => [
        'SKU-1' => 'My Permission 1',
        'SKU-2' => 'My Permission 2',
    ],

    'pianote_membership_product_ids' => [],
    'guitareo_membership_product_ids' => [],

    'intercom_attribute_name_to_pack_product_ids' => [
        'pack_attribute_name' => [], // an array of product ids, ex: [1,2,3]
    ],

    'infusionsoft_app_name' => '',
    'infusionsoft_api_key' => '',
    'infusionsoft_db_on' => '',

    'maropost_disable_syncing' => env('APP_DEBUG', false),

    'maropost_user_id_custom_field_name' => 'musora_user_id',

    'maropost_member_active_tag' => [
        'pianote' => 'Pianote - Customer - Member - Active',
        'guitareo' => 'Guitareo - Customer - Member - Active',
        'drumeo' => 'Drumeo - Customer - Member - Active',
    ],
    'maropost_member_expired_tag' => [
        'pianote' => 'Pianote - Customer - Member - ExMember',
        'guitareo' => 'Guitareo - Customer - Member - ExMember',
        'drumeo' => 'Drumeo - Customer - Member - ExMember',
    ],

    'maropost_product_brand_list_id_map' => [
        'pianote' => 33,
        'guitareo' => 32,
        'drumeo' => 31,
    ],

    // in general a user can only be 1 membership type:
    // 1 month recurring, 3 month recurring, 1 year recurring, or lifetime
    'membership_type_product_sku_maropost_tag_mapping' => [
        'pianote' => [
            'recurring' => [
                'PIANOTE-MEMBERSHIP-1-MONTH' => 'Pianote - Customer - Member - 1 Month',
                'PIANOTE-MEMBERSHIP-1-YEAR' => 'Pianote - Customer - Member - 1 Year',
            ],
            'lifetime' => [
                'PIANOTE-MEMBERSHIP-LIFETIME' => 'Pianote - Customer - Member - Lifetime',
                'PIANOTE-MEMBERSHIP-LIFETIME-EXISTING-MEMBERS' => 'Pianote - Customer - Member - Lifetime',
            ],
        ],
        'guitareo' => [
            'recurring' => [
                'GUITAREO-1-MONTH-MEMBERSHIP' => 'Guitareo - Customer - Member - 1 Month',
                'GUITAREO-1-YEAR-MEMBERSHIP' => 'Guitareo - Customer - Member - 1 Year',
                'GUITAREO-6-MONTH-MEMBERSHIP' => 'Guitareo - Customer - Member - 6 Month',
            ],
            'lifetime' => [
                'GUITAREO-LIFETIME-MEMBERSHIP' => 'Guitareo - Customer - Member - Lifetime',
            ],
        ],
    ],

    'one_time_product_sku_maropost_tag_mapping' => [
        'pianote' => [
            '500-songs-in-5-days' => 'Pianote - Customer - Pack - 500 Songs',
            '500-songs-in-5-days-99' => 'Pianote - Customer - Pack - 500 Songs',
        ],

        'guitareo' => [
            'GTME-JAN-2018-SEMESTER' => 'Guitareo - Customer - Pack - GTME',
            'GTME-JUL-2018-SEMESTER' => 'Guitareo - Customer - Pack - GTME - 2018 July',
            'GTME-OCT-2018-SEMESTER' => 'Guitareo - Customer - Pack - GTME - 2018 October',
            'AGME-JAN-2019-SEMESTER' => 'Guitareo - Customer - Pack - AGME - 2019 January',
            'AGME-MAY-2019-SEMESTER' => 'Guitareo - Customer - Pack - AGME - 2019 May',
            'JUSTIN-VIP-WORKSHOP-2019' => 'Guitareo - Customer - Event - Justin VIP - 2019',
            'GUITAR-SYSTEM' => 'Guitareo - Customer - Pack - GS',
        ],

        'drumeo' => [
            'festival-VIP' => 'Drumeo - Customer - Event - Drumeo Festival - 2020',
            'rock-drumming-masterclass-july-2019-semester' => 'Drumeo - Customer - Pack - RDM - July 2019',
            'drum-technique-made-easy-april-2019-semester' => 'Drumeo - Customer - Pack - DTME - April 2018',
            '2019VIPWEEK-5' => 'Drumeo - Customer - Event - VIP - 2019 July 14',
            '2019VIPWEEK-4' => 'Drumeo - Customer - Event - VIP - 2019 July 7',
            '2019VIPWEEK-3' => 'Drumeo - Customer - Event - VIP - 2019 June 23',
            '2019VIPWEEK-2' => 'Drumeo - Customer - Event - VIP - 2019 June 9',
            'rock-drumming-masterclass' => 'Drumeo - Customer - Pack - RDM',
            'MAM-DIGI' => 'Drumeo - Customer - Hudson Pack - Sucherman MM',
            'HGAF-DIGI' => 'Drumeo - Customer - Hudson Pack - Petrillo HGF',
            'ICM-DIGI' => 'Drumeo - Customer - Hudson Pack - Portnoy Motion',
            'BTC-DIGI' => 'Drumeo - Customer - Hudson Pack - Spears Chops',
            'GHFAL-DIGI' => 'Drumeo - Customer - Hudson Pack - Igoe HGAF',
            'TG-DIGI' => 'Drumeo - Customer - Hudson Pack - Mangini Grid',
            'CC-DIGI' => 'Drumeo - Customer - Hudson Pack - Lang CC',
            'AOADS-DIGI' => 'Drumeo - Customer - Hudson Pack - Peart Solo',
            'TLOD-DIGI' => 'Drumeo - Customer - Hudson Pack - Greb TLOD',
            'BeginnerBook' => 'Drumeo - Customer - Accessory - Book - BBDB',
            'independence-made-easy-july-2018' => 'Drumeo - Customer - Pack - IME - July 2018',
            '2018VIPWEEK-6' => 'Drumeo - Customer - Event - VIP - 2018 September 30',
            '2018VIPWEEK-5' => 'Drumeo - Customer - Event - VIP - 2018 September 23',
            '2018VIPWEEK-4' => 'Drumeo - Customer - Event - VIP - 2018 July 1',
            '2018VIPWEEK-3' => 'Drumeo - Customer - Event - VIP - 2018 June 24',
            '2018VIPWEEK-2' => 'Drumeo - Customer - Event - VIP - 2018 June 10',
            '2018VIPWEEK-1' => 'Drumeo - Customer - Event - VIP - 2018 June 3',
            'drum-technique-made-easy' => 'Drumeo - Customer - Pack - DTME',
            'independence-made-easy-july-2017' => 'Drumeo - Customer - Pack - IME - July 2017',
            '2017VIPWEEK-4' => 'Drumeo - Customer - Event - VIP - 2017 July 9',
            '2017VIPWEEK-3' => 'Drumeo - Customer - Event - VIP - 2017 June 25',
            'independence-made-easy' => 'Drumeo - Customer - Pack - IME',
            'Drumeo-Snare-2017' => 'Drumeo - Customer - Accessory - Misc - Dunnett Snare 2017',
            '2017VIPWEEK-2' => 'Drumeo - Customer - Event - VIP - 2017 June 11',
            '2017VIPWEEK-1' => 'Drumeo - Customer - Event - VIP - 2017 May 28',
            'JIMRILEY' => 'Drumeo - Customer - Accessory - Book - Jim Riley Survival Guide',
            'DTAC' => 'Drumeo - Customer - Accessory - Misc - Drumtacs',
            'VIPWEEK4july252916' => 'Drumeo - Customer - Event - VIP - 2016 July 25',
            'VIPWEEK3june131716' => 'Drumeo - Customer - Event - VIP - 2016 June 13',
            'VIPWEEK2july4816' => 'Drumeo - Customer - Event - VIP - 2016 July 4',
            'practicepad' => 'Drumeo - Customer - Accessory - Misc - P4 Practice Pad',
            'VIPWEEK1may232716' => 'Drumeo - Customer - Event - VIP - 2016 May 23',
            'DLM-UPSELL-2-month' => 'Drumeo - Customer - Member - BiMonthly',
            'BENNY2' => 'Drumeo - Customer - Event - VIP Benny Greb - 2',
            'BENNY' => 'Drumeo - Customer - Event - VIP Benny Greb - 1',
            'MASTERCLASS-TRJ' => 'Drumeo - Customer - Event - TRJ Masterclass',
            'DLM-3-month' => 'Drumeo - Customer - Member - 3 Month',
            'DLM-6-month' => 'Drumeo - Customer - Member - 6 Month',
            'DLM-teachers-training-pack' => 'Drumeo - Customer - Pack - DFT',
            'VIPWEEKsept131915' => 'Drumeo - Customer - Event - VIP - 2015 September',
            'VIPWEEKaug232915' => 'Drumeo - Customer - Event - VIP - 2015 August',
            'VIPWEEKjuly192515' => 'Drumeo - Customer - Event - VIP - 2015 July',
            'VIPWEEKjune212715' => 'Drumeo - Customer - Event - VIP - 2015 June',
            'Drumeo-Snare' => 'Drumeo - Customer - Accessory - Misc - Dunnett Snare 1',
            'PASS-12' => 'Drumeo - Customer - Accessory - Misc - 1 Year Card',
            'VIPWEEK' => 'Drumeo - Customer - Event - VIP - 2014 - Week 3',
            'VIPWEEKAug2430141st' => 'Drumeo - Customer - Event - VIP - 2014 - Week 2',
            'VIPWEEKjuly2026141st' => 'Drumeo - Customer - Event - VIP - 2014 - Week 1',
            'MM-DIGI' => 'Drumeo - Customer - Pack - MM',
            'MM-DIGIPHYS' => 'Drumeo - Customer - Pack - MM',
            'DLM-Trial-1-month' => 'Drumeo - Customer - Member - Free Trial',
            'DSYS2-DIGI' => 'Drumeo - Customer - Pack - DS',
            'DSYS2-DIGIPHYS' => 'Drumeo - Customer - Pack - DS',
            'DLM-Lifetime' => 'Drumeo - Customer - Member - Lifetime',
            'DLM-1-year' => 'Drumeo - Customer - Member - Annual',
            'DLM-1-month' => 'Drumeo - Customer - Member - Monthly',
            'SD-DIGI' => 'Drumeo - Customer - Pack - SD',
            'SD-DIGIPHYS' => 'Drumeo - Customer - Pack - SD',
            'LDS-DIGI' => 'Drumeo - Customer - Pack - LDS',
            'LDS-DIGIPHYS' => 'Drumeo - Customer - Pack - LDS',
            'MOELLERMS-DIGI' => 'Drumeo - Customer - Pack - MMS',
            'MOELLERMS-DIGIPHYS' => 'Drumeo - Customer - Pack - MMS',
            'JDS-DIGI' => 'Drumeo - Customer - Pack - JDS',
            'JDS-DIGIPHYS' => 'Drumeo - Customer - Pack - JDS',
            'DLM-6mo' => 'Drumeo - Customer - Accessory - Misc - 6 Month Ticket',
            'DTUNESYS-DIGI' => 'Drumeo - Customer - Pack - DTS',
            'DTUNESYS-DIGIPHYS' => 'Drumeo - Customer - Pack - DTS',
            'DRUDSYS-DIGI' => 'Drumeo - Customer - Pack - DRS',
            'DRUDSYS-DIGIPHYS' => 'Drumeo - Customer - Pack - DRS',
            'DPAS-DIGI' => 'Drumeo - Customer - Pack - DPAS',
            'DPAS-DIGIPHYS' => 'Drumeo - Customer - Pack - DPAS',
            'DFS-DIGI' => 'Drumeo - Customer - Pack - DFS',
            'DFS-DIGIPHYS' => 'Drumeo - Customer - Pack - DFS',
            'TCM-DIGI' => 'Drumeo - Customer - Pack - CM',
            'TCM-DIGIPHYS' => 'Drumeo - Customer - Pack - CM',
            'BDS2-DIGI' => 'Drumeo - Customer - Pack - BDS',
            'BDS2-DIGIPHYS' => 'Drumeo - Customer - Pack - BDS',
            'PASS-1' => 'Drumeo - Customer - Accessory - Misc - 1 Month Edge Card',
            'PASS-6' => 'Drumeo - Customer - Accessory - Misc - 6 Month'
        ],

    ],
];