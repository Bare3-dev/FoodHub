<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different user tiers and endpoint types.
    | All time windows are in seconds.
    |
    */

    'enabled' => env('RATE_LIMITING_ENABLED', true),

    'cache_prefix' => 'rate_limit',

    'progressive_penalties' => [
        'enabled' => true,
        'durations' => [
            1 => 300,    // 5 minutes
            2 => 900,    // 15 minutes  
            3 => 1800,   // 30 minutes
            4 => 3600,   // 1 hour
            5 => 7200,   // 2 hours
        ],
        'max_violations_stored' => 24 * 3600, // 24 hours
    ],

    'tiers' => [
        'unauthenticated' => [
            'general' => ['ip' => ['limit' => 15, 'window' => 60]],
            'login' => ['ip' => ['limit' => 5, 'window' => 900]],
            'password_reset' => ['ip' => ['limit' => 3, 'window' => 3600]],
            'mfa_verify' => ['ip' => ['limit' => 3, 'window' => 300]],
            'mfa_request' => ['ip' => ['limit' => 2, 'window' => 60]],
        ],
        
        'customer' => [
            'general' => [
                'ip' => ['limit' => 50, 'window' => 60], 
                'user' => ['limit' => 400, 'window' => 60]
            ],
            'login' => [
                'ip' => ['limit' => 5, 'window' => 900], 
                'user' => ['limit' => 10, 'window' => 900]
            ],
            'password_reset' => [
                'ip' => ['limit' => 3, 'window' => 3600], 
                'user' => ['limit' => 5, 'window' => 3600]
            ],
            'mfa_verify' => [
                'ip' => ['limit' => 5, 'window' => 300], 
                'user' => ['limit' => 5, 'window' => 300]
            ],
            'mfa_request' => [
                'ip' => ['limit' => 3, 'window' => 60], 
                'user' => ['limit' => 5, 'window' => 3600]
            ],
        ],
        
        'internal_staff' => [
            'general' => [
                'ip' => ['limit' => 100, 'window' => 60], 
                'user' => ['limit' => 5000, 'window' => 60]
            ],
            'login' => [
                'ip' => ['limit' => 10, 'window' => 900], 
                'user' => ['limit' => 20, 'window' => 900]
            ],
            'password_reset' => [
                'ip' => ['limit' => 5, 'window' => 3600], 
                'user' => ['limit' => 10, 'window' => 3600]
            ],
            'mfa_verify' => [
                'ip' => ['limit' => 10, 'window' => 300], 
                'user' => ['limit' => 10, 'window' => 300]
            ],
            'mfa_request' => [
                'ip' => ['limit' => 5, 'window' => 60], 
                'user' => ['limit' => 10, 'window' => 3600]
            ],
        ],
        
        'super_admin' => [
            'general' => [
                'ip' => ['limit' => 200, 'window' => 60], 
                'user' => ['limit' => 10000, 'window' => 60]
            ],
            'login' => [
                'ip' => ['limit' => 20, 'window' => 900], 
                'user' => ['limit' => 50, 'window' => 900]
            ],
            'password_reset' => [
                'ip' => ['limit' => 10, 'window' => 3600], 
                'user' => ['limit' => 20, 'window' => 3600]
            ],
            'mfa_verify' => [
                'ip' => ['limit' => 20, 'window' => 300], 
                'user' => ['limit' => 20, 'window' => 300]
            ],
            'mfa_request' => [
                'ip' => ['limit' => 10, 'window' => 60], 
                'user' => ['limit' => 20, 'window' => 3600]
            ],
        ],
    ],

    'security_endpoints' => [
        'login' => [
            'per_email_limit' => 3,
            'per_email_window' => 300, // 5 minutes
        ],
        'password_reset' => [
            'per_email_limit' => 1,
            'per_email_window' => 600, // 10 minutes
        ],
    ],

    'monitoring' => [
        'log_violations' => true,
        'log_channel' => 'single', // Use default log channel
    ],
]; 