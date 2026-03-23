---
outline: deep
---

# Configuration

LatticePHP uses environment variables (`.env` files) and PHP configuration files (`config/*.php`) to control framework behavior. This page documents every configuration option.

## Environment Files

The `.env` file in your project root defines environment-specific values. LatticePHP loads it at boot time via `EnvLoader::loadFile()`.

```bash
APP_NAME=MyApp        # Used in logs, emails, and metadata
APP_ENV=local         # local, staging, production
APP_DEBUG=true        # Show detailed errors (disable in production)
APP_URL=http://localhost:8000
```

::: warning
Never set `APP_DEBUG=true` in production. It exposes stack traces, configuration values, and internal paths in error responses.
:::

### Environment Detection

Access environment values anywhere:

```php
// In config files
'name' => env('APP_NAME', 'LatticePHP'),

// In application code (prefer config over raw env)
$config->get('app.name');
```

## Configuration Files

The `config/` directory contains PHP files that return arrays. Each file controls a specific subsystem.

### app.php -- Application

```php
return [
    'name'     => env('APP_NAME', 'LatticePHP'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale'   => env('APP_LOCALE', 'en'),
    'key'      => env('APP_KEY'),
];
```

### database.php -- Database Connections

Supports SQLite, MySQL, and PostgreSQL simultaneously:

```php
return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', 'database/database.sqlite'),
            'foreign_key_constraints' => true,
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'lattice'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'lattice'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'schema' => 'public',
        ],
    ],
];
```

### auth.php -- Authentication

```php
return [
    // Guard driver: jwt, pat, or api-key
    'guard' => env('AUTH_GUARD', 'jwt'),

    'jwt' => [
        'secret'      => env('JWT_SECRET'),
        'algorithm'   => env('JWT_ALGORITHM', 'HS256'),
        'access_ttl'  => (int) env('JWT_ACCESS_TTL', 60),      // minutes
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 10080),  // minutes (7 days)
        'issuer'      => env('JWT_ISSUER', env('APP_URL')),
        'audience'    => env('JWT_AUDIENCE'),
        // Asymmetric keys (RS256, ES256)
        'private_key' => env('JWT_PRIVATE_KEY'),
        'public_key'  => env('JWT_PUBLIC_KEY'),
    ],

    'hashing' => [
        'driver' => env('HASH_DRIVER', 'bcrypt'),
        'bcrypt' => ['rounds' => (int) env('BCRYPT_ROUNDS', 12)],
        'argon2id' => ['memory' => 65536, 'time' => 4, 'threads' => 1],
    ],

    'user_model' => \App\Models\User::class,
];
```

::: tip
For production, use asymmetric keys (RS256 or ES256). This allows verification services to validate tokens with only the public key. See [Authentication](auth.md) for key generation instructions.
:::

### cors.php -- Cross-Origin Resource Sharing

```php
return [
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type', 'Authorization', 'X-Requested-With',
        'X-Workspace-Id', 'X-Tenant-Id', 'Accept', 'Origin',
    ],
    'exposed_headers' => [
        'X-RateLimit-Limit', 'X-RateLimit-Remaining',
        'X-RateLimit-Reset', 'X-Request-Id',
    ],
    'max_age' => (int) env('CORS_MAX_AGE', 86400),
    'supports_credentials' => (bool) env('CORS_CREDENTIALS', false),
];
```

::: danger
Never use `CORS_ALLOWED_ORIGINS=*` in production. Set specific origins: `CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com`
:::

### cache.php -- Cache Layer

```php
return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'array'  => ['driver' => 'array'],
        'file'   => ['driver' => 'file', 'path' => storage_path('cache')],
        'redis'  => ['driver' => 'redis', 'connection' => env('CACHE_REDIS_CONNECTION', 'cache')],
    ],
    'prefix' => env('CACHE_PREFIX', 'lattice_cache_'),
];
```

### queue.php -- Job Queue

```php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync'     => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => env('QUEUE_TABLE', 'jobs')],
        'redis'    => ['driver' => 'redis', 'connection' => 'default'],
    ],
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'table'  => 'failed_jobs',
    ],
];
```

### logging.php -- Logging

```php
return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack'  => ['driver' => 'stack', 'channels' => explode(',', env('LOG_STACK', 'daily,stderr'))],
        'daily'  => ['driver' => 'daily', 'path' => storage_path('logs/lattice.log'), 'days' => (int) env('LOG_DAYS', 14)],
        'stderr' => ['driver' => 'stderr', 'formatter' => env('LOG_STDERR_FORMATTER', 'json')],
    ],
];
```

### mail.php -- Email

```php
return [
    'default'  => env('MAIL_MAILER', 'log'),
    'mailers'  => [
        'smtp' => [
            'transport' => 'smtp',
            'host'      => env('MAIL_HOST', '127.0.0.1'),
            'port'      => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username'  => env('MAIL_USERNAME'),
            'password'  => env('MAIL_PASSWORD'),
        ],
        'log' => ['transport' => 'log'],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'LatticePHP')),
    ],
];
```

### workspace.php -- Workspace Isolation

```php
return [
    'enabled'              => (bool) env('WORKSPACE_ENABLED', true),
    'resolver'             => env('WORKSPACE_RESOLVER', 'header'),  // header or subdomain
    'column'               => env('WORKSPACE_COLUMN', 'workspace_id'),
    'max_members'          => (int) env('WORKSPACE_MAX_MEMBERS', 50),
    'max_per_user'         => (int) env('WORKSPACE_MAX_PER_USER', 10),
    'invite_expires_days'  => (int) env('WORKSPACE_INVITE_EXPIRES', 7),
    'max_pending_invites'  => (int) env('WORKSPACE_MAX_PENDING_INVITES', 20),
];
```

### tenancy.php -- Multi-Tenancy

```php
return [
    'mode'         => env('TENANCY_MODE', 'single_db'),      // single_db or db_per_tenant
    'resolver'     => env('TENANCY_RESOLVER', 'subdomain'),   // subdomain, header, or path
    'base_domain'  => env('TENANCY_BASE_DOMAIN', 'app.localhost'),
    'column'       => env('TENANCY_COLUMN', 'tenant_id'),
    'cache'        => (bool) env('TENANCY_CACHE', true),
    'cache_ttl'    => (int) env('TENANCY_CACHE_TTL', 3600),
];
```

### observability.php -- Tracing, Metrics, Audit

```php
return [
    'tracing' => [
        'enabled'      => (bool) env('TRACING_ENABLED', false),
        'endpoint'     => env('TRACING_ENDPOINT', 'http://localhost:4318'),
        'service_name' => env('TRACING_SERVICE_NAME', env('APP_NAME')),
        'sample_rate'  => (float) env('TRACING_SAMPLE_RATE', 1.0),
    ],
    'metrics' => [
        'enabled' => (bool) env('METRICS_ENABLED', false),
        'prefix'  => env('METRICS_PREFIX', 'lattice'),
    ],
    'audit' => [
        'enabled'        => (bool) env('AUDIT_ENABLED', true),
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),
    ],
];
```

## Configuration Caching

In production, cache configuration to avoid reading `.env` and config files on every request:

```bash
php bin/lattice config:cache    # Generates cached config
php bin/lattice config:clear    # Removes cached config
```

::: warning
After caching config, calls to `env()` outside of config files will return `null`. Always use `env()` only inside `config/*.php` files, and access values via `$config->get('key')` elsewhere.
:::

## Complete Environment Variable Reference

See the `.env.example` file in your project for the full list of all supported environment variables with documentation. The API starter template includes 100+ documented variables covering every subsystem.

## Next Steps

- [Directory Structure](directory-structure.md) -- understand what each folder does
- [Authentication](auth.md) -- configure JWT, OAuth2, and social auth
- [Deployment](deployment.md) -- production configuration checklist
