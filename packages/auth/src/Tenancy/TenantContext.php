<?php

declare(strict_types=1);

namespace Lattice\Auth\Tenancy;

use Lattice\Auth\Models\Tenant;

/**
 * Global tenant context holder for the current request lifecycle.
 *
 * Thread-safe for single-process PHP (FPM/CLI). For long-running workers
 * (RoadRunner, OpenSwoole), reset() MUST be called between requests.
 */
final class TenantContext
{
    private static ?Tenant $current = null;

    /**
     * Set the current tenant for this request.
     */
    public static function set(?Tenant $tenant): void
    {
        self::$current = $tenant;
    }

    /**
     * Get the current tenant, or null if not resolved.
     */
    public static function get(): ?Tenant
    {
        return self::$current;
    }

    /**
     * Get the current tenant's ID, or null if not resolved.
     */
    public static function id(): ?int
    {
        return self::$current?->id;
    }

    /**
     * Reset the tenant context. Must be called between requests in long-running workers.
     */
    public static function reset(): void
    {
        self::$current = null;
    }
}
