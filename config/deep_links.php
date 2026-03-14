<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deep Link Domain
    |--------------------------------------------------------------------------
    |
    | The subdomain that serves deep-link verification files and product
    | share pages. When set, .well-known and /products/{slug} routes only
    | respond on this domain. Leave null to respond on all domains.
    |
    */
    'domain' => env('DEEP_LINK_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Share Link Base URL
    |--------------------------------------------------------------------------
    |
    | Public links shared by the mobile apps should point to the marketing
    | domain that hosts Universal Links / App Links association files.
    |
    */
    'scheme' => env('DEEP_LINK_SCHEME', 'surprisemoi'),

    'share_base_url' => env('DEEP_LINK_SHARE_BASE_URL', 'https://app.surprisemoi.com'),

    'android' => [
        'package_name' => env('ANDROID_APP_PACKAGE_NAME', 'com.teczaleel.surprisemoiapp'),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANDROID_APP_SHA256_CERT_FINGERPRINTS', ''))
        ))),
        'store_url' => env(
            'ANDROID_APP_STORE_URL',
            'https://play.google.com/store/apps/details?id=com.teczaleel.surprisemoiapp'
        ),
    ],

    'ios' => [
        'team_id' => env('IOS_APP_TEAM_ID', ''),
        'bundle_id' => env('IOS_APP_BUNDLE_ID', 'com.teczaleel.surprisemoiapp'),
        'store_url' => env('IOS_APP_STORE_URL', ''),
    ],
];
