<?php

declare(strict_types=1);

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily,stderr')),
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/lattice.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lattice.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_DAYS', 14),
        ],
        'stderr' => [
            'driver' => 'stderr',
            'level' => env('LOG_LEVEL', 'debug'),
            'formatter' => env('LOG_STDERR_FORMATTER', 'json'),
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-inject context into every log entry
    |--------------------------------------------------------------------------
    */
    'context' => [
        'correlation_id' => true,
        'tenant_id' => true,
        'workspace_id' => true,
        'user_id' => true,
        'request_path' => true,
    ],
];
