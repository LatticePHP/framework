<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a tenant in the multi-tenancy system.
 *
 * Each tenant has a unique slug (used for subdomain resolution),
 * an optional custom domain, and a JSON settings bag for per-tenant configuration.
 */
final class Tenant extends Model
{
    /** @var string */
    protected $table = 'tenants';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
