<?php

declare(strict_types=1);

return [
    'default' => env('CACHE_STORE', 'file'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('CACHE_REDIS_CONNECTION', 'cache'),
            'lock_connection' => env('CACHE_REDIS_LOCK_CONNECTION', 'default'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'lattice_cache_'),
];
