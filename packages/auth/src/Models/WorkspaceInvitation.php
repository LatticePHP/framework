<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lattice\Database\Model;

final class WorkspaceInvitation extends Model
{
    protected $table = 'workspace_invitations';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The workspace this invitation belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The user who sent this invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Whether this invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Whether this invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Whether this invitation is still pending (not accepted and not expired).
     */
    public function isPending(): bool
    {
        return !$this->isAccepted() && !$this->isExpired();
    }

    /**
     * Mark the invitation as accepted.
     */
    public function markAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }
}
