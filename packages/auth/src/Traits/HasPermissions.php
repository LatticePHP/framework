<?php

declare(strict_types=1);

namespace Lattice\Auth\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lattice\Auth\Models\Permission;
use Lattice\Authorization\Gate;
use Lattice\Authorization\PolicyRegistry;

trait HasPermissions
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function hasPermission(string $permission): bool
    {
        // Check direct permissions
        if ($this->permissions->contains('slug', $permission)) {
            return true;
        }

        // Check role permissions
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('slug', $permission)) {
                return true;
            }
        }

        return false;
    }

    public function givePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('slug', $permission)->first();
            if ($permissionModel === null) {
                throw new \InvalidArgumentException("Permission '{$permission}' not found.");
            }
            $permission = $permissionModel;
        }

        if (!$this->permissions->contains($permission->getKey())) {
            $this->permissions()->attach($permission);
            $this->load('permissions');
        }
    }

    public function revokePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('slug', $permission)->first();
            if ($permissionModel === null) {
                return;
            }
            $permission = $permissionModel;
        }

        $this->permissions()->detach($permission);
        $this->load('permissions');
    }

    public function can(string $ability, mixed $subject = null): bool
    {
        // Check super admin bypass
        if (method_exists($this, 'hasRole') && $this->hasRole($this->getSuperAdminRole())) {
            return true;
        }

        // Check direct and role-based permissions
        return $this->hasPermission($ability);
    }

    protected function getSuperAdminRole(): string
    {
        return 'super-admin';
    }
}
