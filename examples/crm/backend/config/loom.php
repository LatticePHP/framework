<?php

declare(strict_types=1);

return [
    'enabled' => env('LOOM_ENABLED', true),
    'path' => env('LOOM_PATH', 'loom'),
    'middleware' => ['auth'],
    'redis' => [
        'connection' => env('LOOM_REDIS_CONNECTION', 'default'),
    ],
];
