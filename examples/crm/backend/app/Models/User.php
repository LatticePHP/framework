<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lattice\Auth\Models\Workspace;
use Lattice\Auth\Models\WorkspaceMember;
use Lattice\Auth\Traits\HasPermissions;
use Lattice\Auth\Traits\HasRoles;
use Lattice\Database\Model;
use Lattice\Database\Traits\Auditable;

final class User extends Model
{
    use HasRoles;
    use HasPermissions;
    use Auditable;

    /** @var list<array<string, mixed>> */
    protected static array $auditLog = [];
    protected static int|string|null $auditUserId = null;
    /** @var array{ip_address: ?string, user_agent: ?string, url: ?string, method: ?string}|null */
    protected static ?array $auditRequestMeta = null;

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

    /**
     * Contacts owned by this user.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'owner_id');
    }

    /**
     * Deals owned by this user.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'owner_id');
    }
}
