<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Paystack is used for processing payments. The webhook secret is used
    | to verify that webhooks are genuinely from Paystack.
    |
    */

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'currency' => env('PAYSTACK_CURRENCY', 'GHS'),
        'callback_url' => env('PAYSTACK_CALLBACK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | KairosAfrika SMS Service
    |--------------------------------------------------------------------------
    |
    | KairosAfrika is used for sending OTP SMS messages for phone verification.
    |
    */

    'kairosafrika' => [
        'api_url' => env('KAIROSAFRIKA_API_URL', 'https://api.kairosafrika.com/v1'),
        'api_key' => env('KAIROSAFRIKA_API_KEY'),
        'api_secret' => env('KAIROSAFRIKA_API_SECRET'),
        'api_version' => env('KAIROSAFRIKA_API_VERSION', '2025-08-01'),
        'sender_name' => env('KAIROSAFRIKA_SENDER_NAME', 'SurpriseMoi'),
        'log_only' => env('SMS_LOG_ONLY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Maps API
    |--------------------------------------------------------------------------
    |
    | Google Maps Platform is used for location services including place
    | autocomplete and reverse geocoding (converting coordinates to addresses).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Social Login)
    |--------------------------------------------------------------------------
    |
    | The Web client ID is used to verify Google ID tokens sent from the
    | mobile app. Google Sign-In on mobile sends tokens with the web
    | client audience, so this must be the Web client ID (not Android).
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'places_api_url' => 'https://maps.googleapis.com/maps/api/place',
        'geocoding_api_url' => 'https://maps.googleapis.com/maps/api/geocode',
    ],

];
