# LatticePHP CRM Example

A full-featured CRM application built **entirely** on the LatticePHP framework. This is the proof that every framework feature works end-to-end.

## Framework Features Used

| Feature | Where Used |
|---------|-----------|
| `Application::configure()->withModules()` | `bootstrap/app.php` |
| `RequestFactory::fromGlobals()` | `public/index.php` |
| `ResponseEmitter::emit()` | `public/index.php` |
| `#[Module]` attribute | `AppModule`, every feature module |
| `#[Controller]` attribute routing | Every controller |
| `#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]` | Every route |
| `#[UseGuards]` pipeline | Every controller (JWT + Workspace) |
| `#[Body] DTO` validated binding | Every create/update endpoint |
| `#[Param] int $id` path binding | Every show/update/delete endpoint |
| `#[CurrentUser] Principal` | Every create endpoint |
| `QueryFilter::fromRequest()` | Every list endpoint |
| `Filterable` trait + `filter()` | Every model |
| `Searchable` trait + `search()` | Contacts, Companies |
| `ResponseFactory::paginated()` | Every list endpoint |
| `ResponseFactory::created()` | Every create endpoint |
| `ResponseFactory::noContent()` | Every delete endpoint |
| `ResponseFactory::json()` | Every detail endpoint |
| `Resource::make()` + `::collection()` | Every endpoint |
| `whenLoaded()` conditional includes | Every resource |
| `Model` extends Eloquent | Every model |
| `BelongsToWorkspace` trait | Every CRM model |
| `Auditable` trait | Every CRM model |
| `SoftDeletes` | Every CRM model |
| `HasFactory` with `Factory` | Contacts, Companies, Deals |
| Eloquent relationships | All models (`belongsTo`, `hasMany`, `morphMany`, `morphTo`) |
| Eloquent scopes | Activity (`upcoming`, `overdue`) |
| `JwtAuthenticationGuard` | Every controller via `#[UseGuards]` |
| `WorkspaceGuard` | Every controller via `#[UseGuards]` |
| `AuthController` (login/register/refresh/me) | Via framework `AuthModule` |
| `Principal` authentication context | Controller parameter injection |
| `Log::info()` structured logging | Every service |
| `abort()` / `abort_if()` / `abort_unless()` | Controllers and services |
| `env()` / `config()` helpers | Config files |
| Validation attributes | Every DTO (`#[Required]`, `#[Email]`, `#[InArray]`, `#[StringType]`, `#[Nullable]`) |
| Database migrations (Illuminate Schema) | All 8 migration files |
| Database seeding | `DatabaseSeeder` with realistic data |
| Model factories | `ContactFactory`, `CompanyFactory`, `DealFactory` |

## Architecture

```
app/
  AppModule.php                    # Root module importing all feature modules
  Models/                          # Eloquent models with framework traits
  Modules/
    Auth/AuthModule.php            # Imports framework AuthModule
    Contacts/                      # Full CRUD + search + filter
    Companies/                     # Full CRUD + search + filter
    Deals/                         # Full CRUD + pipeline + stage transitions
    Activities/                    # Full CRUD + upcoming/overdue
    Notes/                         # Polymorphic CRUD
    Dashboard/                     # Stats + pipeline overview + feed
```

## API Endpoints

### Auth (via framework AuthModule)
- `POST /api/auth/login` - Login with email/password
- `POST /api/auth/register` - Register new user
- `POST /api/auth/refresh` - Refresh JWT token
- `GET /api/auth/me` - Get authenticated user

### Contacts
- `GET /api/contacts` - List with filter/sort/search/pagination
- `POST /api/contacts` - Create contact
- `GET /api/contacts/:id` - Get contact with relationships
- `PUT /api/contacts/:id` - Update contact
- `DELETE /api/contacts/:id` - Soft delete contact
- `GET /api/contacts/search?q=` - Full-text search

### Companies
- `GET /api/companies` - List with filter/sort/search/pagination
- `POST /api/companies` - Create company
- `GET /api/companies/:id` - Get company with relationships
- `PUT /api/companies/:id` - Update company
- `DELETE /api/companies/:id` - Soft delete company
- `GET /api/companies/search?q=` - Full-text search

### Deals
- `GET /api/deals` - List with filter/sort/pagination
- `POST /api/deals` - Create deal
- `GET /api/deals/:id` - Get deal with relationships
- `PUT /api/deals/:id` - Update deal
- `DELETE /api/deals/:id` - Soft delete deal
- `GET /api/deals/pipeline` - Pipeline view grouped by stage
- `POST /api/deals/:id/stage` - Move deal to new stage

### Activities
- `GET /api/activities` - List with filter/sort/pagination
- `POST /api/activities` - Create activity
- `GET /api/activities/:id` - Get activity with relationships
- `PUT /api/activities/:id` - Update activity
- `DELETE /api/activities/:id` - Soft delete activity
- `GET /api/activities/upcoming` - Get upcoming activities
- `GET /api/activities/overdue` - Get overdue activities
- `POST /api/activities/:id/complete` - Mark activity as completed

### Notes
- `GET /api/notes` - List with filter/pagination
- `POST /api/notes` - Create polymorphic note
- `GET /api/notes/:id` - Get note
- `PUT /api/notes/:id` - Update note
- `DELETE /api/notes/:id` - Soft delete note
- `GET /api/notes/for/:type/:entityId` - Get notes for entity

### Dashboard
- `GET /api/dashboard/stats` - CRM overview statistics
- `GET /api/dashboard/pipeline` - Pipeline overview
- `GET /api/dashboard/feed` - Recent activity feed

## Query Parameters

All list endpoints support:
- `?filter[status]=lead` - Filter by field
- `?filter[status]=lead,prospect` - Filter by multiple values
- `?filter[value][gt]=1000` - Range filters
- `?sort=-created_at,last_name` - Sort (- prefix = DESC)
- `?search=john` - Full-text search across searchable columns
- `?include=company,deals` - Eager load relationships
- `?per_page=25&page=2` - Pagination

## Seed Data

- 3 users (admin, manager, rep)
- 1 workspace with all users as members
- 20 companies across industries
- 50 contacts with various statuses and sources
- 30 deals across all pipeline stages
- 40 activities (15 completed, 25 pending)
- 25 notes across contacts, companies, and deals
