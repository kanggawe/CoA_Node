<?php

use CoaProxy\Env;

return [
    // Default NAS IP address if not supplied in API request
    'default_nas' => Env::get('RADIUS_DEFAULT_NAS', '10.10.10.1'),

    // Allowed NAS IP addresses (comma-separated list for strict IP whitelist)
    'allowed_nas' => array_filter(
        array_map('trim', explode(',', Env::get('RADIUS_ALLOWED_NAS', '10.10.10.1')))
    ),

    // RADIUS CoA UDP Port
    'coa_port' => (int) Env::get('RADIUS_COA_PORT', 3799),

    // Shared Secret for RADIUS client communication
    'secret' => Env::get('RADIUS_SECRET', ''),

    // Path to binary radclient
    'radclient_path' => Env::get('RADCLIENT_PATH', '/usr/bin/radclient'),

    // Process execution timeout in seconds
    'timeout' => (int) Env::get('RADIUS_TIMEOUT', 5),

    // Whitelisted RADIUS Attributes allowed in generic CoA request
    'allowed_attributes' => [
        'User-Name',
        'Acct-Session-Id',
        'Mikrotik-Rate-Limit',
        'Mikrotik-Address-List',
        'Session-Timeout',
        'Idle-Timeout',
    ],
];
