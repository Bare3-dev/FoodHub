<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | Environment Variables:
    | - APP_ENV: Determines if we use development or production settings
    | - FRONTEND_URL: Primary frontend domain (e.g., https://foodhub.com)
    | - ADMIN_URL: Admin dashboard domain (e.g., https://admin.foodhub.com)
    | - CORS_ALLOWED_ORIGINS: Comma-separated additional origins
    |
    */

    'paths' => [
        'api/*',
        'auth/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('APP_ENV') === 'production' 
        ? array_filter([
            env('FRONTEND_URL'),
            env('ADMIN_URL'),
            ...array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
        ])
        : [
            // Development origins - common frontend development servers
            'http://localhost:3000',     // React, Next.js default
            'http://localhost:5173',     // Vite default
            'http://localhost:4200',     // Angular default
            'http://localhost:8080',     // Vue CLI default
            'http://localhost:8100',     // Ionic default
            'http://localhost:3001',     // Alternative React port
            'http://localhost:8000',     // Alternative development port
            'http://127.0.0.1:3000',     // IPv4 localhost variants
            'http://127.0.0.1:5173',
            'http://127.0.0.1:4200',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:8100',
            // Custom development domains
            env('FRONTEND_URL', 'http://localhost:3000'),
            env('ADMIN_URL'),
            // Additional origins from environment
            ...array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
        ],

    'allowed_origins_patterns' => env('APP_ENV') === 'production' 
        ? [
            // Production: Only allow specific patterns for subdomains
        ]
        : [
            // Development: Allow common localhost patterns
            '/^http:\/\/localhost:\d+$/',
            '/^http:\/\/127\.0\.0\.1:\d+$/',
            '/^https?:\/\/.*\.ngrok\.io$/',          // Ngrok tunnels
            '/^https?:\/\/.*\.ngrok-free\.app$/',    // New Ngrok domains
            '/^https?:\/\/.*\.vercel\.app$/',        // Vercel preview deployments
            '/^https?:\/\/.*\.netlify\.app$/',       // Netlify preview deployments
        ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Origin',
        'Cache-Control',
        'Pragma',
        'X-Forwarded-For',
        'X-Forwarded-Proto',
        'X-Forwarded-Host',
    ],

    'exposed_headers' => [
        'X-Pagination-Total',
        'X-Pagination-Per-Page',
        'X-Pagination-Current-Page',
        'X-Pagination-Last-Page',
        'X-Rate-Limit-Limit',
        'X-Rate-Limit-Remaining',
        'X-Rate-Limit-Reset',
        'Retry-After',
    ],

    'max_age' => env('CORS_MAX_AGE', 86400), // 24 hours default

    'supports_credentials' => true, // Required for Sanctum authentication

];
