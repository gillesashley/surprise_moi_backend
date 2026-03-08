<?php

return [
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

    'share_base_url' => env('DEEP_LINK_SHARE_BASE_URL', 'https://surprisemoi.com'),

    'android' => [
        'package_name' => env('ANDROID_APP_PACKAGE_NAME', 'com.surprisemoi.app'),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANDROID_APP_SHA256_CERT_FINGERPRINTS', ''))
        ))),
        'store_url' => env(
            'ANDROID_APP_STORE_URL',
            'https://play.google.com/store/apps/details?id=com.surprisemoi.app'
        ),
    ],

    'ios' => [
        'team_id' => env('IOS_APP_TEAM_ID', 'TEAMID'),
        'bundle_id' => env('IOS_APP_BUNDLE_ID', 'com.surprisemoi.app'),
        'store_url' => env(
            'IOS_APP_STORE_URL',
            'https://apps.apple.com/app/id0000000000'
        ),
    ],
];
