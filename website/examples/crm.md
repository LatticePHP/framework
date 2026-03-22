---
outline: deep
---

# CRM Example Application

The CRM example is a full-featured backend built entirely with LatticePHP framework features. It demonstrates modules, Eloquent, guards, DTOs, response factories, query filters, and the complete request lifecycle.

## Architecture

```
examples/crm/backend/
  app/
    Modules/
      Auth/          # Login, register, JWT tokens
      Contacts/      # Contact management (CRUD + search)
      Companies/     # Company management
      Deals/         # Deal pipeline with stages
      Activities/    # Activity tracking (calls, emails, meetings)
      Tasks/         # Task management with assignments
      Dashboard/     # Analytics and statistics
    Models/          # Eloquent models
    Guards/          # Auth guards
  config/            # Configuration files
  database/
    migrations/      # Schema definitions
    seeders/         # Sample data
  public/
    index.php        # Entry point
  tests/             # E2E tests
```

## Modules

The CRM uses 7 modules, each with its own controller, service, and routes:

```php
#[Module(
    imports: [
        AuthModule::class,
        ContactsModule::class,
        CompaniesModule::class,
        DealsModule::class,
        ActivitiesModule::class,
        TasksModule::class,
        DashboardModule::class,
    ],
)]
final class AppModule {}
```

## Routes

The CRM exposes 40 routes across all modules:

### Auth
| Method | Path | Description |
|---|---|---|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login, returns JWT |
| POST | `/api/auth/refresh` | Refresh access token |
| GET | `/api/auth/me` | Current user profile |

### Contacts
| Method | Path | Description |
|---|---|---|
| GET | `/api/contacts` | List with filters & pagination |
| POST | `/api/contacts` | Create contact |
| GET | `/api/contacts/:id` | Get contact |
| PUT | `/api/contacts/:id` | Update contact |
| DELETE | `/api/contacts/:id` | Delete contact |

### Companies
| Method | Path | Description |
|---|---|---|
| GET | `/api/companies` | List companies |
| POST | `/api/companies` | Create company |
| GET | `/api/companies/:id` | Get company |
| PUT | `/api/companies/:id` | Update company |
| DELETE | `/api/companies/:id` | Delete company |

### Deals
| Method | Path | Description |
|---|---|---|
| GET | `/api/deals` | List deals |
| POST | `/api/deals` | Create deal |
| GET | `/api/deals/:id` | Get deal |
| PUT | `/api/deals/:id` | Update deal |
| DELETE | `/api/deals/:id` | Delete deal |
| GET | `/api/deals/pipeline` | Pipeline view by stage |
| PUT | `/api/deals/:id/stage` | Move deal to stage |

### Activities & Tasks
| Method | Path | Description |
|---|---|---|
| GET | `/api/activities` | List activities |
| POST | `/api/activities` | Log activity |
| GET | `/api/tasks` | List tasks |
| POST | `/api/tasks` | Create task |
| PUT | `/api/tasks/:id` | Update task |
| PUT | `/api/tasks/:id/complete` | Mark task complete |

### Dashboard
| Method | Path | Description |
|---|---|---|
| GET | `/api/dashboard/stats` | Overview statistics |
| GET | `/api/dashboard/pipeline` | Pipeline summary |

## Key Patterns

### Controller with Guards

```php
#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
final class ContactController
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response
    {
        $contacts = $this->service->list($request->query->all());
        return ResponseFactory::json(['data' => $contacts]);
    }

    #[Post('/')]
    public function create(#[Body] CreateContactDto $dto): Response
    {
        $contact = $this->service->create($dto);
        return ResponseFactory::created(['data' => $contact]);
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $contact = $this->service->findOrFail($id);
        return ResponseFactory::json(['data' => $contact]);
    }
}
```

### DTO Validation

```php
final readonly class CreateContactDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $name,

        #[Required]
        #[Email]
        public string $email,

        #[InArray(values: ['lead', 'prospect', 'customer', 'inactive'])]
        public string $status = 'lead',

        #[Nullable]
        public ?string $phone = null,

        #[Nullable]
        public ?int $company_id = null,
    ) {}
}
```

### Query Filtering

```php
// GET /api/contacts?status=lead&sort=-created_at&search=alice
final class ContactService
{
    public function list(array $filters): array
    {
        $query = Contact::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['sort'])) {
            $dir = str_starts_with($filters['sort'], '-') ? 'desc' : 'asc';
            $col = ltrim($filters['sort'], '-');
            $query->orderBy($col, $dir);
        }

        return $query->get()->toArray();
    }
}
```

### Deal Pipeline

```php
#[Get('/pipeline')]
public function pipeline(): Response
{
    $stages = ['qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
    $pipeline = [];

    foreach ($stages as $stage) {
        $deals = Deal::where('stage', $stage)->get();
        $pipeline[$stage] = [
            'count' => $deals->count(),
            'value' => $deals->sum('value'),
            'deals' => $deals->toArray(),
        ];
    }

    return ResponseFactory::json(['data' => $pipeline]);
}
```

## Running the CRM

```bash
cd examples/crm/backend

# Install dependencies
composer install

# Run migrations
php lattice migrate

# Seed sample data
php lattice db:seed

# Start server
php lattice serve

# Run tests
vendor/bin/phpunit
```

## Test Coverage

The CRM includes 76 E2E tests covering:
- User registration and login
- JWT token refresh
- CRUD operations for all entities
- Query filtering and search
- Deal pipeline management
- Activity logging
- Task lifecycle (create, update, complete)
- Dashboard statistics
- Error handling (404, 422, 401)
