<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lattice\Database\Model;

final class Permission extends Model
{
    protected $table = 'permissions';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'guard_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
