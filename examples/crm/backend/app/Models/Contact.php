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

final class Contact extends Model
{
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    use Filterable;
    use BelongsToWorkspace;
    use Auditable;

    /** @var list<array<string, mixed>> */
    protected static array $auditLog = [];
    protected static int|string|null $auditUserId = null;
    /** @var array{ip_address: ?string, user_agent: ?string, url: ?string, method: ?string}|null */
    protected static ?array $auditRequestMeta = null;

    protected $table = 'contacts';

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_id',
        'title',
        'status',
        'source',
        'owner_id',
        'workspace_id',
        'tags',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tags' => 'array',
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['first_name', 'last_name', 'email'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['status', 'company_id', 'owner_id', 'source'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'first_name', 'last_name', 'email'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the full name of the contact.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
