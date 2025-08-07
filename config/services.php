<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | POS System Integrations
    |--------------------------------------------------------------------------
    |
    | Configuration for various Point of Sale (POS) system integrations
    | including Square, Toast, and local POS systems.
    |
    */

    'square' => [
        'api_url' => env('SQUARE_API_URL', 'https://api.square.com/v2'),
        'api_key' => env('SQUARE_API_KEY'),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'webhook_secret' => env('SQUARE_WEBHOOK_SECRET'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'), // sandbox or production
    ],

    'toast' => [
        'api_url' => env('TOAST_API_URL', 'https://api.toasttab.com/v1'),
        'api_key' => env('TOAST_API_KEY'),
        'restaurant_id' => env('TOAST_RESTAURANT_ID'),
        'webhook_secret' => env('TOAST_WEBHOOK_SECRET'),
        'environment' => env('TOAST_ENVIRONMENT', 'sandbox'), // sandbox or production
    ],

    'local_pos' => [
        // Dynamic configuration for local POS systems
        // Each POS system will have its own configuration key
        'default' => [
            'api_url' => env('LOCAL_POS_API_URL'),
            'api_key' => env('LOCAL_POS_API_KEY'),
            'webhook_secret' => env('LOCAL_POS_WEBHOOK_SECRET'),
        ],
        // Example for specific local POS systems
        'pos_system_1' => [
            'api_url' => env('LOCAL_POS_1_API_URL'),
            'api_key' => env('LOCAL_POS_1_API_KEY'),
            'webhook_secret' => env('LOCAL_POS_1_WEBHOOK_SECRET'),
        ],
        'pos_system_2' => [
            'api_url' => env('LOCAL_POS_2_API_URL'),
            'api_key' => env('LOCAL_POS_2_API_KEY'),
            'webhook_secret' => env('LOCAL_POS_2_WEBHOOK_SECRET'),
        ],
    ],

];
