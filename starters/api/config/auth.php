<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Authentication Guard
    |--------------------------------------------------------------------------
    | Supported: "jwt", "pat", "api-key"
    */
    'default' => env('AUTH_GUARD', 'jwt'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'jwt' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'pat' => [
            'driver' => 'pat',
            'provider' => 'users',
        ],
        'api-key' => [
            'driver' => 'api-key',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => \App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    */
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),

        // Asymmetric keys (RS256, ES256)
        'private_key' => env('JWT_PRIVATE_KEY'),
        'public_key' => env('JWT_PUBLIC_KEY'),

        // Token TTL in minutes
        'access_ttl' => (int) env('JWT_ACCESS_TTL', 60),        // 1 hour
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 10080),   // 7 days

        'issuer' => env('JWT_ISSUER', env('APP_URL')),
        'audience' => env('JWT_AUDIENCE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Hashing
    |--------------------------------------------------------------------------
    | Supported: "bcrypt", "argon2id"
    */
    'hashing' => [
        'driver' => env('HASH_DRIVER', 'bcrypt'),
        'bcrypt' => [
            'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        ],
        'argon2id' => [
            'memory' => 65536,
            'time' => 4,
            'threads' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policies
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_number' => true,
        'require_special' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    */
    'super_admin_role' => env('SUPER_ADMIN_ROLE', 'super-admin'),
];
