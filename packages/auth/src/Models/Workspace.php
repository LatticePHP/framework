<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lattice\Database\Model;

final class Workspace extends Model
{
    protected $table = 'workspaces';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'settings',
        'logo_url',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * The user who owns this workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * All members of this workspace (via pivot).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role', 'joined_at', 'invited_by')
            ->using(WorkspaceMember::class);
    }

    /**
     * Pending and accepted invitations for this workspace.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * Check whether a user is a member of this workspace.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the workspace role for a given user, or null if not a member.
     */
    public function getMemberRole(User $user): ?string
    {
        $member = $this->members()->where('user_id', $user->id)->first();

        return $member?->pivot->role;
    }

    /**
     * Add a user as a member with the given role.
     */
    public function addMember(User $user, string $role = 'member', ?int $invitedBy = null): void
    {
        $this->members()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
            'invited_by' => $invitedBy,
        ]);
    }

    /**
     * Remove a member from the workspace.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Check if the given user is the owner.
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
