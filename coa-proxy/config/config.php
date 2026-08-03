<?php

use CoaProxy\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', false),

    // Bearer token used to authenticate incoming API requests
    'api_token' => Env::get('COA_API_TOKEN', ''),

    // Allowed IP addresses allowed to call this API (comma-separated string)
    'allowed_ips' => array_filter(
        array_map('trim', explode(',', Env::get('COA_ALLOWED_IPS', '127.0.0.1')))
    ),

    // Log configuration
    'log' => [
        'level' => Env::get('LOG_LEVEL', 'info'),
        'file' => Env::get('LOG_FILE', dirname(__DIR__) . '/storage/logs/coa.log'),
    ],

    // Rate Limiting (default 60 requests / minute / IP)
    'rate_limit' => [
        'max_requests' => (int) Env::get('RATE_LIMIT_MAX', 60),
        'decay_seconds' => (int) Env::get('RATE_LIMIT_DECAY', 60),
    ],

    // Request size limit (64 KB)
    'max_request_body_size' => 64 * 1024,
];
