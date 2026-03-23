---
outline: deep
---

# Database

## Overview

LatticePHP's database layer is built directly on Illuminate Database (Eloquent). The `Lattice\Database\Model` base class extends `Illuminate\Database\Eloquent\Model` with zero method overrides, giving you full Eloquent compatibility. On top of this, LatticePHP provides traits for workspace scoping, tenant isolation, audit logging, full-text search, and query filtering.

## IlluminateDatabaseManager

`Lattice\Database\Illuminate\IlluminateDatabaseManager` wraps Laravel's Capsule to bootstrap Eloquent outside of Laravel:

```php
use Lattice\Database\Illuminate\IlluminateDatabaseManager;

$db = new IlluminateDatabaseManager([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'myapp',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

// Query builder
$db->table('contacts')->where('status', 'lead')->get();

// Schema builder
$db->schema()->create('contacts', function ($table) {
    $table->id();
    $table->string('email')->unique();
    $table->timestamps();
});

// Named connections
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'testing');
$db->connection('testing')->table('users')->get();
```

For testing, use SQLite in-memory:

```php
$db = new IlluminateDatabaseManager([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
```

## Base Model

`Lattice\Database\Model` extends Eloquent's `Model` directly. All Eloquent features work as-is:

```php
use Lattice\Database\Model;

final class Contact extends Model
{
    protected $table = 'contacts';

    protected $fillable = ['first_name', 'last_name', 'email', 'status'];

    protected $casts = [
        'tags' => 'array',
        'deleted_at' => 'datetime',
    ];
}
```

## Relationships

Standard Eloquent relationships work unchanged. From the CRM `Contact` model:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Contact extends Model
{
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
}
```

The `User` model also uses `belongsToMany` for workspaces with a pivot:

```php
public function workspaces(): BelongsToMany
{
    return $this->belongsToMany(Workspace::class, 'workspace_members')
        ->withPivot('role', 'joined_at', 'invited_by')
        ->using(WorkspaceMember::class);
}
```

## SoftDeletes

Use Illuminate's `SoftDeletes` trait directly:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

final class Contact extends Model
{
    use SoftDeletes;

    protected $casts = ['deleted_at' => 'datetime'];
}
```

```php
$contact->delete();                  // Soft delete (sets deleted_at)
$contact->restore();                 // Restore soft-deleted record
$contact->forceDelete();             // Permanent delete
Contact::withTrashed()->get();       // Include soft-deleted
Contact::onlyTrashed()->get();       // Only soft-deleted
```

## Searchable Trait

`Lattice\Database\Search\Searchable` provides Scout-like search using SQL LIKE queries:

```php
use Lattice\Database\Search\Searchable;

final class Contact extends Model
{
    use Searchable;

    protected array $searchable = ['first_name', 'last_name', 'email'];
}
```

Usage:

```php
// Search across all searchable columns
$results = Contact::search('john')->get();

// Chain with Eloquent query methods
$results = Contact::search('john')->where('status', 'lead')->get();

// Get searchable columns programmatically
$contact->getSearchableColumns(); // ['first_name', 'last_name', 'email']
```

`SearchBuilder` generates `WHERE (first_name LIKE '%john%' OR last_name LIKE '%john%' OR email LIKE '%john%')`.

## Filterable Trait and QueryFilter

`Lattice\Database\Filter\Filterable` + `Lattice\Database\Filter\QueryFilter` provide Spatie-style query filtering from request parameters.

### Model Setup

```php
use Lattice\Database\Filter\Filterable;

final class Contact extends Model
{
    use Filterable;

    protected array $allowedFilters = ['status', 'company_id', 'owner_id', 'source'];
    protected array $allowedSorts = ['created_at', 'first_name', 'last_name', 'email'];
    protected array $searchable = ['first_name', 'last_name', 'email'];
}
```

### Supported Query Parameters

```
?filter[status]=lead                    # Exact match
?filter[status]=lead,prospect           # whereIn
?filter[company_id]=null                # whereNull
?filter[value][gt]=1000                 # Range: greater than
?filter[value][gte]=1000                # Range: greater than or equal
?filter[value][lt]=5000                 # Range: less than
?filter[value][lte]=5000                # Range: less than or equal
?filter[created_at][from]=2024-01-01    # Range: from date
?filter[created_at][to]=2024-12-31      # Range: to date
?sort=-created_at,last_name             # Sort: - prefix = DESC
?search=john                            # LIKE across searchable columns
?include=company,tags                   # Eager load relations
?per_page=25&page=2                     # Pagination
```

### Controller Usage

```php
use Lattice\Database\Filter\QueryFilter;

#[Get('/contacts')]
public function index(Request $request): Response
{
    $filter = QueryFilter::fromRequest($request->query);
    $query = Contact::filter($filter);
    $contacts = $query->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

    return Response::json($contacts);
}
```

`QueryFilter::fromRequest()` parses the query string. `Contact::filter()` applies the filter to the model's query builder, respecting `$allowedFilters` and `$allowedSorts`. Disallowed filters are silently ignored.

> **Note:** `QueryFilter` enforces a `max_per_page` cap (default 100) to prevent unbounded queries. Override it globally with `QueryFilter::setMaxPerPage(200)` in your bootstrap if needed.

## BelongsToWorkspace

`Lattice\Database\Traits\BelongsToWorkspace` auto-scopes all queries to the current workspace and sets `workspace_id` on create:

```php
use Lattice\Database\Traits\BelongsToWorkspace;

final class Contact extends Model
{
    use BelongsToWorkspace;
}
```

How it works:
- Adds a global scope: `WHERE workspace_id = :current_workspace`
- On create: automatically sets `workspace_id` from `WorkspaceContext::id()`
- The `WorkspaceGuard` in the pipeline sets `WorkspaceContext` per-request

For testing, set the workspace ID statically:

```php
Contact::$testWorkspaceId = 42;
```

Override the column name if needed:

```php
public static function getWorkspaceColumn(): string
{
    return 'team_id';
}
```

## BelongsToTenant

`Lattice\Database\Traits\BelongsToTenant` works identically to `BelongsToWorkspace` but resolves from `TenantContext` (set by `TenantGuard`):

```php
use Lattice\Database\Traits\BelongsToTenant;

final class Invoice extends Model
{
    use BelongsToTenant;
}
```

Query without tenant scope for admin operations:

```php
Invoice::withoutTenantScope()->where('status', 'overdue')->get();
```

## Auditable Trait

`Lattice\Database\Traits\Auditable` automatically logs create/update/delete events to an `audit_logs` table:

```php
use Lattice\Database\Traits\Auditable;

final class Contact extends Model
{
    use Auditable;

    // Optionally exclude sensitive fields from audit
    protected array $auditExclude = ['password', 'secret_key'];
}
```

Each audit entry captures:
- `user_id` -- the acting user
- `action` -- `created`, `updated`, or `deleted`
- `auditable_type` / `auditable_id` -- polymorphic reference
- `old_values` / `new_values` -- what changed (sensitive fields excluded)
- `ip_address`, `user_agent`, `url`, `method` -- request metadata

> **Note:** As of v1.1, the `Auditable` trait uses an internal backing store. Models no longer need to declare `$auditLog`, `$auditUserId`, or `$auditRequestMeta` static properties — just `use Auditable;`.

Query audit logs via the polymorphic relation:

```php
$contact->auditLogs()->latest()->get();
```

For testing:

```php
Contact::setAuditUserId('user-1');
Contact::clearAuditLog();
$logs = Contact::getAuditLog(); // In-memory log entries
```

## CRM Contact Model (Complete Example)

The CRM example app's `Contact` model demonstrates all traits together:

```php
final class Contact extends Model
{
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    use Filterable;
    use BelongsToWorkspace;
    use Auditable;

    protected $table = 'contacts';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'company_id', 'title', 'status', 'source',
        'owner_id', 'workspace_id', 'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected array $searchable = ['first_name', 'last_name', 'email'];
    protected array $allowedFilters = ['status', 'company_id', 'owner_id', 'source'];
    protected array $allowedSorts = ['created_at', 'first_name', 'last_name', 'email'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
```

## Migrations

Migrations use Illuminate's schema builder. Each module owns its migrations:

```php
// database/migrations/2024_01_01_create_contacts_table.php
$schema->create('contacts', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->unique();
    $table->string('phone')->nullable();
    $table->foreignId('company_id')->nullable()->constrained();
    $table->foreignId('owner_id')->nullable()->constrained('users');
    $table->foreignId('workspace_id')->constrained();
    $table->string('status')->default('lead');
    $table->string('source')->nullable();
    $table->json('tags')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

Run migrations:

```bash
bin/lattice migrate
bin/lattice migrate:rollback
bin/lattice migrate:fresh    # Drop all tables and re-migrate
```

## Factories and Seeders

Use Illuminate's `HasFactory` trait for model factories:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class Contact extends Model
{
    use HasFactory;
}
```

Define a factory:

```php
final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'status' => fake()->randomElement(['lead', 'prospect', 'customer']),
        ];
    }
}
```

Usage in tests:

```php
Contact::factory()->create();                    // Single record
Contact::factory()->count(5)->create();          // 5 records
Contact::factory()->create(['status' => 'lead']); // Override attributes
```

## Pagination

Use Eloquent's built-in pagination with `QueryFilter`:

```php
$filter = QueryFilter::fromRequest($request->query);
$query = Contact::filter($filter);
$paginated = $query->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

return Response::json([
    'data' => $paginated->items(),
    'meta' => [
        'total' => $paginated->total(),
        'per_page' => $paginated->perPage(),
        'current_page' => $paginated->currentPage(),
        'last_page' => $paginated->lastPage(),
    ],
]);
```

Default `per_page` is 15. Override via query string: `?per_page=25&page=2`.

## CrudService

`Lattice\Database\Crud\CrudService` provides a base class for mechanical CRUD operations. Services with no custom business logic inherit `find()`, `create()`, `update()`, and `delete()` unchanged. Override lifecycle hooks for custom behaviour.

```php
use Lattice\Database\Crud\CrudService;

final class ContactService extends CrudService
{
    protected function model(): string
    {
        return Contact::class;
    }

    // Optional: eager-load relations on create/update responses
    protected function responseRelations(): array
    {
        return ['company', 'owner'];
    }

    // Optional: lifecycle hooks
    protected function afterCreate(Model $model, Principal $user): void
    {
        // e.g., dispatch an event or send a notification
    }
}
```

Features:
- **Transaction-wrapped** -- create, update, and delete run inside a database transaction (atomic with hooks)
- **Auto owner assignment** -- sets `owner_id` from the authenticated `Principal` on create
- **Null-filtering** -- partial updates ignore null fields from the DTO
- **Constraint error handling** -- unique constraint violations return 422 instead of 500
- **Lifecycle hooks** -- `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`

Available hooks:

| Hook | Signature |
|------|-----------|
| `beforeCreate` | `(array &$data, Principal $user): void` |
| `afterCreate` | `(Model $model, Principal $user): void` |
| `beforeUpdate` | `(Model $model, array &$data): void` |
| `afterUpdate` | `(Model $model): void` |
| `beforeDelete` | `(Model $model): void` |
| `afterDelete` | `(int $id): void` |

::: tip
For simple CRUD modules, pair `CrudService` with `CrudController` (see [HTTP API](http-api.md#crudcontroller)) to get a full REST API with zero boilerplate.
:::
