<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    | Use ['*'] to allow all origins, or list specific domains:
    | ['https://app.example.com', 'https://admin.example.com']
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Workspace-Id',
        'X-Tenant-Id',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Request-Id',
    ],

    'max_age' => (int) env('CORS_MAX_AGE', 86400), // 24 hours

    'supports_credentials' => (bool) env('CORS_CREDENTIALS', false),
];
