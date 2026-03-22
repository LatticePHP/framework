<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lattice\Database\Model;
use Lattice\Database\Search\Searchable;
use Lattice\Database\Filter\Filterable;
use Lattice\Database\Traits\BelongsToWorkspace;
use Lattice\Database\Traits\Auditable;

final class Note extends Model
{
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    use Filterable;
    use BelongsToWorkspace;
    use Auditable;

    protected $table = 'notes';

    /** @var list<string> */
    protected $fillable = [
        'content',
        'notable_type',
        'notable_id',
        'author_id',
        'workspace_id',
        'is_pinned',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_pinned' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['content'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['notable_type', 'notable_id', 'author_id', 'is_pinned'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'updated_at'];

    /**
     * Get the owning notable model (Contact, Company, or Deal).
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who authored this note.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
