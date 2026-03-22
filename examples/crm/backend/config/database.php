<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', 'database/crm.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],
];
