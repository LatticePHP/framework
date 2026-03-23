<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lattice\Database\Model;
use Lattice\Database\Search\Searchable;
use Lattice\Database\Filter\Filterable;
use Lattice\Database\Traits\BelongsToWorkspace;
use Lattice\Database\Traits\Auditable;

final class Deal extends Model
{
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    use Filterable;
    use BelongsToWorkspace;
    use Auditable;

    protected $table = 'deals';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'value',
        'currency',
        'stage',
        'probability',
        'expected_close_date',
        'actual_close_date',
        'contact_id',
        'company_id',
        'owner_id',
        'workspace_id',
        'lost_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['title'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['stage', 'contact_id', 'company_id', 'owner_id', 'currency'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'title', 'value', 'expected_close_date', 'stage'];

    /**
     * Pipeline stages in order.
     */
    public const array STAGES = [
        'lead',
        'qualified',
        'proposal',
        'negotiation',
        'closed_won',
        'closed_lost',
    ];

    public const array CLOSED_STAGES = ['closed_won', 'closed_lost'];

    public const array STAGE_PROBABILITIES = [
        'lead' => 10,
        'qualified' => 25,
        'proposal' => 50,
        'negotiation' => 75,
        'closed_won' => 100,
        'closed_lost' => 0,
    ];

    public static function probabilityForStage(string $stage): int
    {
        return self::STAGE_PROBABILITIES[$stage] ?? 0;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /**
     * Check if this deal is in a closed stage.
     */
    public function isClosed(): bool
    {
        return in_array($this->stage, self::CLOSED_STAGES, true);
    }

    /**
     * Check if this deal was won.
     */
    public function isWon(): bool
    {
        return $this->stage === 'closed_won';
    }
}
