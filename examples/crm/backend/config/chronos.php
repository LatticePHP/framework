<?php

declare(strict_types=1);

return [
    'enabled' => env('CHRONOS_ENABLED', true),
    'path' => env('CHRONOS_PATH', 'chronos'),
    'middleware' => ['auth'],
];
