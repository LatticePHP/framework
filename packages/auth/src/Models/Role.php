<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lattice\Database\Model;

final class Role extends Model
{
    protected $table = 'roles';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'guard_name',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
