<?php

declare(strict_types=1);

return [
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        'ttl' => (int) env('JWT_TTL', 3600),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 86400),
    ],
    'user_model' => \App\Models\User::class,
];
