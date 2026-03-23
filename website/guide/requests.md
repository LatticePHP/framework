---
outline: deep
---

# Requests

The `Lattice\Http\Request` object represents an incoming HTTP request. It provides methods for accessing the URL, headers, query parameters, body, and authenticated user.

## Accessing the Request

The request is auto-injected into controller methods when you type-hint it (no attribute needed):

```php
use Lattice\Http\Request;

#[Get('/')]
public function index(Request $request): Response
{
    $page = $request->getQuery('page', '1');
    $status = $request->getQuery('status');
    // ...
}
```

## Query Parameters

```php
// Single parameter with optional default
$page = $request->getQuery('page', '1');
$status = $request->getQuery('status');          // null if not present

// All query parameters as an array
$all = $request->query;                          // ['page' => '1', 'status' => 'lead']
```

Use the `#[Query]` attribute for named parameter extraction:

```php
#[Get('/search')]
public function search(#[Query] string $q, #[Query] int $page = 1): Response
{
    // GET /search?q=hello&page=2
    // $q = "hello", $page = 2
}
```

## Path Parameters

Use `#[Param]` to extract values from the URL path. Type coercion is automatic:

```php
#[Get('/:id')]
public function show(#[Param] int $id): array
{
    // GET /api/contacts/42
    // $id = 42 (int, not "42")
    return ContactResource::make(Contact::findOrFail($id))->toArray();
}

#[Get('/by-email/:email')]
public function findByEmail(#[Param] string $email): array
{
    return ['email' => $email];
}
```

## Request Body

For JSON request bodies, use `#[Body]` with a DTO:

```php
#[Post('/')]
public function store(#[Body] CreateContactDto $dto): Response
{
    // $dto is already deserialized and validated
    $contact = Contact::create([
        'name' => $dto->name,
        'email' => $dto->email,
    ]);
    return ResponseFactory::created(['data' => $contact]);
}
```

See [Validation](validation.md) for the complete DTO validation guide.

## Headers

```php
// Single header
$contentType = $request->getHeader('Content-Type');
$workspace = $request->getHeader('X-Workspace-Id');

// All headers
$headers = $request->headers;
```

Use `#[Header]` for named header extraction:

```php
#[Get('/')]
public function index(#[Header] string $acceptLanguage): Response
{
    // Extracts the Accept-Language header
}
```

## Bearer Token

```php
$token = $request->bearerToken();
// Extracts the token from "Authorization: Bearer <token>"
// Returns null if no Bearer token is present
```

## Authenticated User

Use `#[CurrentUser]` to inject the authenticated principal:

```php
use Lattice\Auth\Principal;
use Lattice\Routing\Attributes\CurrentUser;

#[Post('/')]
public function store(
    #[Body] CreateContactDto $dto,
    #[CurrentUser] Principal $user,
): Response {
    $contact = Contact::create([
        'name' => $dto->name,
        'owner_id' => $user->getId(),
    ]);
    return ResponseFactory::created(['data' => $contact]);
}
```

The principal is set by the guard in the pipeline. If no guard has authenticated the request, accessing `#[CurrentUser]` results in a 401 or 403 response.

## Request Method and Path

```php
$request->getMethod();    // "GET", "POST", "PUT", "DELETE"
$request->getPath();      // "/api/contacts/42"
```

## Parameter Resolution Summary

| Source | Attribute | Example | Auto-coercion |
|---|---|---|---|
| URL path | `#[Param]` | `/:id` -> `int $id` | Yes |
| Query string | `#[Query]` | `?page=2` -> `int $page` | Yes |
| Request body | `#[Body]` | JSON -> DTO | Yes + validation |
| Headers | `#[Header]` | Header value -> `string` | Yes |
| Auth context | `#[CurrentUser]` | Guard -> `Principal` | N/A |
| Auto-inject | (none needed) | `Request $request` | N/A |

## Next Steps

- [HTTP & Routing](http-api.md) -- controllers, responses, and route definitions
- [Validation](validation.md) -- DTO validation with `#[Body]`
- [Authentication](auth.md) -- how guards set the `#[CurrentUser]` principal
