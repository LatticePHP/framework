<?php

declare(strict_types=1);

namespace Lattice\Database\Traits;

use Illuminate\Database\Eloquent\Builder;
use Lattice\Auth\Tenancy\TenantContext;

/**
 * Automatically scopes queries to the current tenant and sets tenant_id on create.
 *
 * Resolves the current tenant from TenantContext, which is populated by
 * TenantGuard during the request pipeline.
 *
 * Usage:
 *
 *     class Invoice extends Model
 *     {
 *         use BelongsToTenant;
 *     }
 *
 * Override getTenantColumn() to customize the column name.
 * Override resolveCurrentTenantId() to change how the current tenant is resolved.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-scope queries to current tenant
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = static::resolveCurrentTenantId();

            if ($tenantId !== null) {
                $builder->where(static::getTenantColumn(), $tenantId);
            }
        });

        // Auto-set tenant_id on create
        // NOTE: We use a captured class reference because static:: inside a static
        // closure registered via Eloquent events does not late-static-bind to the
        // model class; it resolves to the trait-user's class at definition time
        // which may differ when Eloquent fires the event from the base Model.
        $callingClass = static::class;

        static::creating(static function (mixed $model) use ($callingClass): void {
            $tenantId = null;
            if (property_exists($callingClass, 'testTenantId') && isset($callingClass::$testTenantId)) {
                $tenantId = $callingClass::$testTenantId;
            } else {
                $tenantId = TenantContext::id();
            }

            $column = $callingClass::getTenantColumn();

            if ($tenantId !== null && !$model->{$column}) {
                $model->{$column} = $tenantId;
            }
        });
    }

    /**
     * Get the tenant foreign key column name.
     */
    public static function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    /**
     * Resolve the current tenant ID from the application context.
     *
     * Uses TenantContext (set by TenantGuard) as the primary source.
     * Falls back to a static test override for testing scenarios.
     */
    protected static function resolveCurrentTenantId(): ?int
    {
        // Check for a static override (useful for testing)
        if (property_exists(static::class, 'testTenantId') && isset(static::$testTenantId)) {
            return static::$testTenantId;
        }

        // Resolve from TenantContext (set by TenantGuard in the pipeline)
        return TenantContext::id();
    }

    /**
     * Query without tenant scoping. Use with caution — typically for admin operations.
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
