<?php

return [
    // Send the session cookie over HTTPS only. Turn on once the edge serves TLS.
    'cookie_secure' => (bool) env('COOKIE_SECURE', false),

    // Session lifetime in hours (fixed, not sliding).
    'session_hours' => 8,

    // Used only by the seeder to create the first IT administrator.
    'admin' => [
        'username' => env('IAM_ADMIN_USERNAME', 'itadmin'),
        'email' => env('IAM_ADMIN_EMAIL', 'it@concord.local'),
        'password' => env('IAM_ADMIN_PASSWORD'),
    ],
    
    // Explicit opt-in for local demo identities. Never enable in production.
    'demo_seed_enabled' => env('IAM_DEMO_SEED', false),
];
