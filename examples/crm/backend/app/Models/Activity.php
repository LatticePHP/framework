<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lattice\Database\Model;
use Lattice\Database\Search\Searchable;
use Lattice\Database\Filter\Filterable;
use Lattice\Database\Traits\BelongsToWorkspace;
use Lattice\Database\Traits\Auditable;

final class Activity extends Model
{
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    use Filterable;
    use BelongsToWorkspace;
    use Auditable;

    protected $table = 'activities';

    /** @var list<string> */
    protected $fillable = [
        'type',
        'subject',
        'description',
        'due_date',
        'completed_at',
        'contact_id',
        'deal_id',
        'owner_id',
        'workspace_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['subject', 'description'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['type', 'contact_id', 'deal_id', 'owner_id'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'due_date', 'subject', 'type'];

    public const array TYPES = [
        'call',
        'email',
        'meeting',
        'task',
        'note',
        'follow_up',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Check if this activity is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->completed_at !== null) {
            return false;
        }

        return $this->due_date !== null && $this->due_date->isPast();
    }

    /**
     * Check if this activity is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Scope: upcoming activities (not completed, due in the future).
     */
    public function scopeUpcoming($query): void
    {
        $query->whereNull('completed_at')
            ->where('due_date', '>=', now())
            ->orderBy('due_date', 'asc');
    }

    /**
     * Scope: overdue activities (not completed, past due date).
     */
    public function scopeOverdue($query): void
    {
        $query->whereNull('completed_at')
            ->where('due_date', '<', now())
            ->orderBy('due_date', 'asc');
    }
}
