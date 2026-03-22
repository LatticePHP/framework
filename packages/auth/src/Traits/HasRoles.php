<?php

declare(strict_types=1);

namespace Lattice\Auth\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lattice\Auth\Models\Role;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('slug', $role);
    }

    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $roleModel = Role::where('slug', $role)->first();
            if ($roleModel === null) {
                throw new \InvalidArgumentException("Role '{$role}' not found.");
            }
            $role = $roleModel;
        }

        if (!$this->roles->contains($role->getKey())) {
            $this->roles()->attach($role);
            $this->load('roles');
        }
    }

    public function removeRole(string|Role $role): void
    {
        if (is_string($role)) {
            $roleModel = Role::where('slug', $role)->first();
            if ($roleModel === null) {
                return;
            }
            $role = $roleModel;
        }

        $this->roles()->detach($role);
        $this->load('roles');
    }

    /** @return list<string> */
    public function getRoleNames(): array
    {
        return $this->roles->pluck('slug')->toArray();
    }
}
