<?php

return [
    /*
    |--------------------------------------------------------------------------
    | General Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains general security settings for the application
    | including HTTPS enforcement, security headers, input sanitization,
    | and security logging preferences.
    |
    */

    'force_https' => env('FORCE_HTTPS', true),
    
    'security_logging' => env('SECURITY_LOGGING', true),

    'security_headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        
        'hsts' => [
            'enabled' => true,
            'max_age' => 31536000, // 1 year
            'include_subdomains' => true,
            'preload' => true,
        ],
        
        'content_security_policy' => [
            'enabled' => true,
            'report_only' => env('CSP_REPORT_ONLY', false),
            'report_uri' => env('CSP_REPORT_URI'),
        ],
        
        'referrer_policy' => 'strict-origin-when-cross-origin',
        
        'permissions_policy' => [
            'camera' => '()',
            'microphone' => '()',
            'geolocation' => '()',
            'payment' => '()',
        ],
    ],

    'input_sanitization' => [
        'enabled' => env('INPUT_SANITIZATION_ENABLED', true),
        
        'max_input_length' => env('MAX_INPUT_LENGTH', 10000),
        
        'auto_block_critical_threats' => env('AUTO_BLOCK_CRITICAL_THREATS', true),
        
        'threat_detection' => [
            'sql_injection' => true,
            'xss_protection' => true,
            'path_traversal' => true,
            'command_injection' => true,
            'nosql_injection' => true,
            'suspicious_patterns' => true,
        ],
        
        'safe_routes' => [
            '/health',
            '/ping',
            '/status',
            '/api/sanctum/csrf-cookie',
        ],
    ],

    'encryption' => [
        'sensitive_fields' => [
            'phone',
            'address',
            'payment_method_details',
            'credit_card_last_four',
            'bank_account_details',
            'personal_notes',
            'loyalty_card_number',
        ],
        
        'payment_encryption' => [
            'algorithm' => 'AES-256-CBC',
            'store_cvv' => false, // Never store CVV
            'tokenize_cards' => true,
            'mask_display' => true,
        ],
        
        'key_rotation' => [
            'enabled' => env('KEY_ROTATION_ENABLED', false),
            'schedule' => env('KEY_ROTATION_SCHEDULE', 'monthly'),
            'backup_old_keys' => true,
        ],
    ],

    'security_monitoring' => [
        'incident_logging' => [
            'enabled' => true,
            'log_channel' => env('SECURITY_LOG_CHANNEL', 'single'),
            'include_request_data' => true,
            'sanitize_sensitive_data' => true,
        ],
        
        'threat_detection' => [
            'brute_force_threshold' => [
                'email' => 5, // attempts per 15 minutes
                'ip' => 10,   // attempts per 15 minutes
            ],
            'pattern_spike_threshold' => [
                'authentication_failure' => 20,
                'authorization_failure' => 15,
                'suspicious_activity' => 10,
                'sql_injection_attempt' => 5,
                'xss_attempt' => 5,
            ],
        ],
        
        'automated_response' => [
            'auto_block_ips' => env('AUTO_BLOCK_IPS', true),
            'block_duration' => env('IP_BLOCK_DURATION', 3600), // 1 hour
            'alert_on_critical' => env('ALERT_ON_CRITICAL', true),
        ],
    ],

    'data_protection' => [
        'pii_encryption' => true,
        'payment_data_encryption' => true,
        'audit_trail' => true,
        'data_retention' => [
            'logs' => env('LOG_RETENTION_DAYS', 90),
            'security_incidents' => env('SECURITY_INCIDENT_RETENTION_DAYS', 365),
            'audit_trail' => env('AUDIT_TRAIL_RETENTION_DAYS', 2555), // 7 years
        ],
    ],

    'compliance' => [
        'gdpr' => [
            'enabled' => env('GDPR_COMPLIANCE', true),
            'data_controller' => env('GDPR_DATA_CONTROLLER', 'FoodHub Restaurant Delivery'),
            'privacy_policy_url' => env('PRIVACY_POLICY_URL'),
            'contact_email' => env('PRIVACY_CONTACT_EMAIL', 'privacy@foodhub.com'),
        ],
        
        'pci_dss' => [
            'enabled' => env('PCI_DSS_COMPLIANCE', true),
            'level' => env('PCI_DSS_LEVEL', 4),
            'tokenization' => true,
            'audit_logging' => true,
        ],
    ],

    'api_security' => [
        'versioning' => [
            'current_version' => 'v1',
            'deprecated_versions' => [],
            'sunset_policy' => '6 months notice',
        ],
        
        'authentication' => [
            'token_lifetime' => env('API_TOKEN_LIFETIME', 3600), // 1 hour
            'refresh_token_lifetime' => env('REFRESH_TOKEN_LIFETIME', 2592000), // 30 days
            'max_tokens_per_user' => env('MAX_TOKENS_PER_USER', 5),
        ],
        
        'rate_limiting' => [
            'enabled' => env('RATE_LIMITING_ENABLED', true),
            'default_tier' => 'customer',
            'burst_protection' => true,
        ],
    ],
]; 