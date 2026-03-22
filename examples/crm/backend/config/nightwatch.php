<?php

declare(strict_types=1);

return [
    'enabled' => env('NIGHTWATCH_ENABLED', true),
    'path' => env('NIGHTWATCH_PATH', 'nightwatch'),
    'storage_path' => env('NIGHTWATCH_STORAGE', storage_path('nightwatch')),
    'mode' => env('NIGHTWATCH_MODE', 'auto'), // auto, dev, prod
    'retention_days' => env('NIGHTWATCH_RETENTION', 7),
    'middleware' => ['auth'],
];
