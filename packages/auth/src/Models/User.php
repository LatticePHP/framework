<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lattice\Auth\Traits\HasPermissions;
use Lattice\Auth\Traits\HasRoles;
use Lattice\Database\Model;

class User extends Model
{
    use HasRoles;
    use HasPermissions;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Workspaces this user is a member of.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role', 'joined_at', 'invited_by')
            ->using(WorkspaceMember::class);
    }

    /**
     * Workspaces this user owns.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }
}
