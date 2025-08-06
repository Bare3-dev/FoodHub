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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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
    | Payment Gateway Services
    |--------------------------------------------------------------------------
    |
    | Configuration for various payment gateways used in the application.
    | Each gateway has its own API credentials and webhook settings.
    |
    */

    'mada' => [
        'merchant_id' => env('MADA_MERCHANT_ID'),
        'api_key' => env('MADA_API_KEY'),
        'webhook_secret' => env('MADA_WEBHOOK_SECRET'),
        'environment' => env('MADA_ENVIRONMENT', 'sandbox'),
    ],

    'stc_pay' => [
        'merchant_id' => env('STC_PAY_MERCHANT_ID'),
        'api_key' => env('STC_PAY_API_KEY'),
        'merchant_key' => env('STC_PAY_MERCHANT_KEY'),
        'environment' => env('STC_PAY_ENVIRONMENT', 'sandbox'),
    ],

    'apple_pay' => [
        'merchant_id' => env('APPLE_PAY_MERCHANT_ID'),
        'api_key' => env('APPLE_PAY_API_KEY'),
        'webhook_secret' => env('APPLE_PAY_WEBHOOK_SECRET'),
        'environment' => env('APPLE_PAY_ENVIRONMENT', 'sandbox'),
    ],

    'google_pay' => [
        'merchant_id' => env('GOOGLE_PAY_MERCHANT_ID'),
        'api_key' => env('GOOGLE_PAY_API_KEY'),
        'webhook_secret' => env('GOOGLE_PAY_WEBHOOK_SECRET'),
        'environment' => env('GOOGLE_PAY_ENVIRONMENT', 'sandbox'),
    ],

];
