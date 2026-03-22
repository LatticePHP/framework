<?php

declare(strict_types=1);

namespace Lattice\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class WorkspaceMember extends Pivot
{
    protected $table = 'workspace_members';

    public $incrementing = true;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'joined_at',
        'invited_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /**
     * The workspace this membership belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The user who is a member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who invited this member.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
