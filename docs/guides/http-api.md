# HTTP API

## Controllers

Controllers are classes annotated with `#[Controller]` that define a route prefix. Methods annotated with route attributes handle individual endpoints.

From the CRM's `ContactController`:

```php
<?php
declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Models\Contact;
use App\Modules\Contacts\Dto\CreateContactDto;
use App\Modules\Contacts\Dto\UpdateContactDto;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;
use Lattice\Auth\Principal;

#[Controller('/api/contacts')]
final class ContactController
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response { /* ... */ }

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response { /* ... */ }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response { /* ... */ }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateContactDto $dto): Response { /* ... */ }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response { /* ... */ }
}
```

Controllers must be listed in a module's `controllers` array to be discovered.

## CRUD Controller Base Class

For standard REST resources, extend `Lattice\Http\Crud\CrudController`:

```php
use Lattice\Http\Crud\CrudController;

#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController extends CrudController
{
    public function __construct(private readonly ContactService $service) {}

    protected function service(): CrudService { return $this->service; }
    protected function resourceClass(): string { return ContactResource::class; }
    protected function modelClass(): string { return Contact::class; }
    protected function indexRelations(): array { return ['company']; }
    protected function showRelations(): array { return ['company', 'deals', 'owner']; }

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
    {
        return $this->storeResponse($this->service->create($dto, $user));
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateContactDto $dto): Response
    {
        return $this->updateResponse($this->service->update($id, $dto));
    }

    // Custom endpoints only — index, show, destroy are inherited
}
```

The base class provides:
- `GET /` — paginated, filtered listing via QueryFilter
- `GET /:id` — single resource with eager-loaded relations
- `DELETE /:id` — soft-delete, returns 204
- `storeResponse()` / `updateResponse()` — auto-wrap in `{data: ...}` with eager-loaded relations

## Route Attributes

Five HTTP method attributes are available:

| Attribute | HTTP Method | Example |
|-----------|------------|---------|
| `#[Get('/')]` | GET | List or read |
| `#[Post('/')]` | POST | Create |
| `#[Put('/:id')]` | PUT | Full update |
| `#[Delete('/:id')]` | DELETE | Remove |
| `#[Get('/search')]` | GET | Custom action |

Path parameters use colon syntax: `/:id`, `/:email`, `/:name`. The integration tests prove both styles work:

```php
#[Get('/contacts/:id')]        // colon-style (preferred)
#[Get('/contacts/{id}')]       // brace-style (also works)
```

Static routes match before parameterized routes. `/api/contacts` (index) matches before `/api/contacts/:id`.

## Parameter Binding

The `ParameterResolver` automatically resolves controller method parameters based on attributes and types.

### #[Body] -- Request Body Deserialization

Deserializes the JSON request body into a DTO. Validation attributes on the DTO are checked automatically.

```php
#[Post('/')]
public function store(#[Body] CreateContactDto $dto): Response
{
    $contact = Contact::create([
        'first_name' => $dto->first_name,
        'last_name' => $dto->last_name,
        'email' => $dto->email,
    ]);
    return ResponseFactory::created(['data' => ContactResource::make($contact)->toArray()]);
}
```

### #[Param] -- Path Parameters

Extracts values from the URL path. Type coercion is automatic -- a parameter typed as `int` receives an integer, not a string.

```php
#[Get('/:id')]
public function show(#[Param] int $id): array
{
    $contact = Contact::findOrFail($id);
    return ContactResource::make($contact)->toArray();
}

#[Get('/by-email/:email')]
public function findByEmail(#[Param] string $email): array
{
    return ['email' => $email];
}
```

Proven in integration tests:

```php
// Int coercion
$response = $this->handleRequest('GET', '/api/test/contacts/1');
$this->assertSame(1, $response->body['id']);  // int, not "1"

// String param
$response = $this->handleRequest('GET', '/api/test/contacts/by-email/hello@world.com');
$this->assertSame('hello@world.com', $response->body['email']);
```

### #[CurrentUser] -- Authenticated Principal

Injects the authenticated user identity set by a guard. Returns a `Principal` or `PrincipalInterface` object.

```php
#[Post('/')]
public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
{
    $contact = $this->service->create($dto, $user);
    return ResponseFactory::created(['data' => ContactResource::make($contact)->toArray()]);
}
```

If no guard has authenticated the request, accessing `#[CurrentUser]` results in a 401 or 403 response.

### #[Query] -- Query String Parameters

Extracts named values from the query string.

### Request -- Auto-Injected

The `Request` object is auto-injected without any attribute when typed as `Lattice\Http\Request`:

```php
#[Get('/')]
public function index(Request $request): Response
{
    $filter = QueryFilter::fromRequest($request->query);
    $contacts = Contact::filter($filter)
        ->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());
    return ResponseFactory::paginated($contacts, ContactResource::class);
}
```

You can also access query parameters directly:

```php
$status = $request->getQuery('status');
$token = $request->bearerToken();
```

## DTOs with Validation

DTOs are plain PHP classes with validation attributes. Two styles are supported.

### Constructor-Based DTOs (recommended)

Properties are declared as constructor parameters. This is the pattern used in the CRM:

```php
<?php
declare(strict_types=1);

namespace App\Modules\Contacts\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateContactDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $first_name,

        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $last_name,

        #[Required]
        #[Email]
        public string $email,

        #[Nullable]
        #[StringType(maxLength: 30)]
        public ?string $phone = null,

        #[Nullable]
        public ?int $company_id = null,

        #[InArray(values: ['lead', 'prospect', 'customer', 'churned', 'inactive'])]
        public string $status = 'lead',

        #[Nullable]
        public ?array $tags = null,
    ) {}
}
```

### Property-Based DTOs

Properties are declared as public class properties (no constructor). The DtoMapper sets them directly:

```php
<?php
declare(strict_types=1);

namespace App\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class PropertyBasedDto
{
    #[Required] #[StringType] public string $name;
    #[Required] #[Email] public string $email;
}
```

Both styles are proven working in integration tests.

### Available Validation Attributes

| Attribute | Purpose | Parameters |
|-----------|---------|------------|
| `#[Required]` | Field must be present and non-null | -- |
| `#[StringType]` | Must be a string | `minLength`, `maxLength` |
| `#[Email]` | Must be valid email format | -- |
| `#[Nullable]` | Field may be null | -- |
| `#[InArray]` | Value must be in allowed list | `values: ['a', 'b', 'c']` |

### Validation Errors

When validation fails, the framework returns a 422 response in RFC 9457 Problem Details format:

```json
{
    "type": "https://httpstatuses.io/422",
    "title": "Unprocessable Entity",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "email": ["The email field must be a valid email address."]
    }
}
```

Default values in DTOs are honored. If a field with a default is omitted from the request body, the default is used:

```php
// DTO has: public string $status = 'active'
// Request body omits status
// Result: $dto->status === 'active'
```

## API Resources

Resources transform models into JSON-safe arrays. Extend `Lattice\Http\Resource` and implement `toArray()`:

```php
<?php
declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Modules\Companies\CompanyResource;
use Lattice\Http\Resource;

final class ContactResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'company' => $this->whenLoaded('company', fn ($company) => CompanyResource::make($company)->toArray()),
            'deals_count' => $this->when(
                $this->resource->relationLoaded('deals'),
                fn () => $this->resource->deals->count(),
            ),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
```

### Resource Methods

| Method | Purpose |
|--------|---------|
| `Resource::make($model)` | Wrap a single model |
| `Resource::collection($models)` | Transform a collection to `list<array>` |
| `Resource::paginatedCollection($paginator)` | Wrap a paginator with meta/links |
| `$this->when($condition, $value)` | Include field conditionally |
| `$this->whenLoaded('relation', $callback)` | Include only if relation is eager-loaded |

Usage:

```php
// Single resource
ContactResource::make($contact)->toArray();

// Collection
ContactResource::collection(Contact::all());

// In a response
ResponseFactory::json(['data' => ContactResource::make($contact)->toArray()]);
```

## ResponseFactory

`Lattice\Http\ResponseFactory` provides static methods for common response types:

```php
// 200 JSON response
ResponseFactory::json(['data' => $contact]);

// 201 Created
ResponseFactory::created(['data' => ContactResource::make($contact)->toArray()]);

// 202 Accepted
ResponseFactory::accepted(['job_id' => $jobId]);

// 204 No Content
ResponseFactory::noContent();

// Paginated with meta and links
ResponseFactory::paginated($contacts->paginate(15), ContactResource::class);

// 422 Validation error
ResponseFactory::validationError(['email' => ['The email is already taken.']]);

// Generic error (RFC 9457 Problem Details)
ResponseFactory::error('Resource not found', 404);

// 429 Too Many Requests with rate limit headers
ResponseFactory::tooManyRequests(limit: 100, reset: time() + 60);
```

### Return Type Conventions

The framework handles different return types automatically:

| Return Type | HTTP Status | Behavior |
|-------------|-------------|----------|
| `array` | 200 | Serialized to JSON |
| `Response` | Whatever you set | Passed through directly |
| `void` | 204 | No Content, empty body |

## Pagination

Use Eloquent's `paginate()` with `ResponseFactory::paginated()`:

```php
#[Get('/')]
public function index(Request $request): Response
{
    $filter = QueryFilter::fromRequest($request->query);
    $contacts = Contact::filter($filter)
        ->with(['company'])
        ->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

    return ResponseFactory::paginated($contacts, ContactResource::class);
}
```

The response includes `data`, `meta`, and `links`:

```json
{
    "data": [
        {"id": 1, "first_name": "Alice", "email": "alice@example.com"}
    ],
    "meta": {
        "total": 42,
        "per_page": 15,
        "current_page": 1,
        "last_page": 3,
        "from": 1,
        "to": 15
    },
    "links": {
        "first": "/?page=1",
        "last": "/?page=3",
        "prev": null,
        "next": "/?page=2"
    }
}
```

## Error Handling

All errors follow RFC 9457 Problem Details format:

```json
{
    "type": "https://httpstatuses.io/404",
    "title": "Not Found",
    "status": 404,
    "detail": "No query results for model [Contact]."
}
```

Exception mapping:

| Exception | Status Code |
|-----------|-------------|
| `ModelNotFoundException` | 404 |
| `ValidationException` | 422 |
| `ForbiddenException` (guard denial) | 403 |
| `UnauthorizedException` | 401 |
| `RuntimeException` | 500 |

The framework catches exceptions at the pipeline level and converts them to Problem Details responses. After a 500 error, subsequent requests continue working normally (proven in integration tests with 6 sequential requests).

## Guards

Protect routes with `#[UseGuards]`:

```php
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Pipeline\Attributes\UseGuards;

#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController
{
    // All routes in this controller require JWT auth + workspace context
}
```

Guards can be applied at the class level (all routes) or method level (individual routes):

```php
#[Get('/health')]
public function health(): array
{
    return ['status' => 'ok'];  // No guard, publicly accessible
}

#[Get('/protected')]
#[UseGuards(guards: [TestAuthGuard::class])]
public function protected(#[CurrentUser] PrincipalInterface $user): array
{
    return ['user_id' => $user->getId()];  // Guard required
}
```

A guard implements `GuardInterface` and sets the `Principal` on the execution context:

```php
final class TestAuthGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        if (!$context instanceof HttpExecutionContext) {
            return false;
        }

        $token = $context->getRequest()->bearerToken();

        if ($token === 'valid-token') {
            $context->setPrincipal(new Principal(id: '1', type: 'user'));
            return true;
        }

        return false;
    }
}
```

## CORS

CORS is configured in `config/cors.php`:

```php
return [
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Workspace-Id',
        'Accept',
        'Origin',
    ],
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Request-Id',
    ],
    'max_age' => (int) env('CORS_MAX_AGE', 86400),
    'supports_credentials' => (bool) env('CORS_CREDENTIALS', false),
];
```

Set specific origins in your `.env`:

```
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```
