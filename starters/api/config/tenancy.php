<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tenancy Mode
    |--------------------------------------------------------------------------
    | How your application handles multi-tenancy:
    |
    | "single_db"     — All tenants in one database, isolated by tenant_id column
    | "db_per_tenant" — Each tenant has its own database
    | "schema"        — Each tenant has its own schema (PostgreSQL only)
    | "none"          — No multi-tenancy (single tenant)
    */
    'mode' => env('TENANCY_MODE', 'single_db'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Strategy
    |--------------------------------------------------------------------------
    | How to determine which tenant a request belongs to:
    |
    | "subdomain"  — tenant.yourdomain.com (e.g., acme.app.com)
    | "domain"     — Custom domain per tenant (e.g., app.acmecorp.com)
    | "header"     — X-Tenant-Id request header
    | "path"       — /tenants/{tenant}/resource URL prefix
    | "jwt"        — tenant_id claim in JWT token
    */
    'resolver' => env('TENANCY_RESOLVER', 'subdomain'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Domain Configuration
    |--------------------------------------------------------------------------
    */
    'domain' => [
        // The main application domain (used to extract subdomain)
        'base' => env('TENANCY_BASE_DOMAIN', 'app.localhost'),

        // Central domains that are NOT tenant-specific (admin panel, marketing site)
        'central' => [
            'admin.' . env('TENANCY_BASE_DOMAIN', 'app.localhost'),
            'www.' . env('TENANCY_BASE_DOMAIN', 'app.localhost'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Database (for db_per_tenant mode)
    |--------------------------------------------------------------------------
    */
    'database' => [
        // Prefix for tenant database names: {prefix}_{tenant_id}
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),

        // Template database to clone for new tenants
        'template' => env('TENANCY_DB_TEMPLATE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Column Name
    |--------------------------------------------------------------------------
    | Column name used in tenant-scoped models (for single_db mode)
    */
    'column' => env('TENANCY_COLUMN', 'tenant_id'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    */
    'model' => env('TENANCY_MODEL', \App\Models\Tenant::class),

    /*
    |--------------------------------------------------------------------------
    | Tenant Cache
    |--------------------------------------------------------------------------
    | Cache resolved tenants to avoid DB lookup on every request
    */
    'cache' => [
        'enabled' => (bool) env('TENANCY_CACHE', true),
        'ttl' => (int) env('TENANCY_CACHE_TTL', 3600), // 1 hour
        'store' => env('TENANCY_CACHE_STORE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Propagation
    |--------------------------------------------------------------------------
    | Automatically propagate tenant context to:
    */
    'propagate' => [
        'queue' => true,     // Queue jobs execute in tenant context
        'events' => true,    // Events carry tenant context
        'workflow' => true,  // Workflow activities inherit tenant
        'mail' => true,      // Mails sent from tenant context
    ],
];
