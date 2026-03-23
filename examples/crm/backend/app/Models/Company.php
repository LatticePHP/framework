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

final class Company extends Model
{
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    use Filterable;
    use BelongsToWorkspace;
    use Auditable;

    protected $table = 'companies';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'domain',
        'industry',
        'size',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'website',
        'owner_id',
        'workspace_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'domain', 'email'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['industry', 'size', 'owner_id', 'country'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'name', 'industry'];

    public const array INDUSTRIES = ['technology', 'finance', 'healthcare', 'manufacturing', 'retail', 'education', 'consulting', 'other'];
    public const array SIZES = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1001+'];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
