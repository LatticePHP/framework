---
outline: deep
---

# OpenAPI Generation

LatticePHP auto-generates OpenAPI 3.1 specifications from your annotated controllers using the `lattice/openapi` package.

## Annotating Controllers

Use `#[ApiOperation]` and `#[ApiResponse]` to document your endpoints:

```php
use Lattice\OpenApi\Attributes\ApiOperation;
use Lattice\OpenApi\Attributes\ApiResponse;

#[Controller('/api/contacts')]
final class ContactController
{
    #[Get('/')]
    #[ApiOperation(summary: 'List contacts', tags: ['Contacts'])]
    #[ApiResponse(status: 200, description: 'Paginated list of contacts')]
    public function index(Request $request): Response { /* ... */ }

    #[Post('/')]
    #[ApiOperation(summary: 'Create a contact', tags: ['Contacts'])]
    #[ApiResponse(status: 201, description: 'Contact created')]
    #[ApiResponse(status: 422, description: 'Validation error')]
    public function store(#[Body] CreateContactDto $dto): Response { /* ... */ }

    #[Get('/:id')]
    #[ApiOperation(summary: 'Get a contact', tags: ['Contacts'])]
    #[ApiResponse(status: 200, description: 'Contact details')]
    #[ApiResponse(status: 404, description: 'Contact not found')]
    public function show(#[Param] int $id): Response { /* ... */ }
}
```

## Generating the Spec

```bash
# Output to stdout
php bin/lattice openapi:generate

# Output to a file
php bin/lattice openapi:generate --output=public/openapi.json
```

## What's Auto-Detected

The `OpenApiGenerator` inspects your controllers and DTOs to build the spec:

| Source | Generated As |
|---|---|
| `#[Controller('/api/contacts')]` | Path prefix |
| `#[Get('/')]`, `#[Post('/')]` | Path + HTTP method |
| `#[Param] int $id` | Path parameter with type |
| `#[Query] string $status` | Query parameter |
| `#[Body] CreateContactDto` | Request body schema (from DTO properties) |
| `#[ApiOperation]` | Summary, description, tags |
| `#[ApiResponse]` | Response descriptions |
| `#[UseGuards]` | Security requirements |
| Validation attributes | Property constraints (`minLength`, `maxLength`, `enum`) |

## Schema Generation

The `SchemaGenerator` converts DTOs to JSON Schema:

```php
// This DTO:
final readonly class CreateContactDto
{
    public function __construct(
        #[Required] #[StringType(minLength: 1, maxLength: 100)]
        public string $name,

        #[Required] #[Email]
        public string $email,

        #[InArray(values: ['lead', 'prospect', 'customer'])]
        public string $status = 'lead',
    ) {}
}

// Generates this schema:
// {
//   "type": "object",
//   "required": ["name", "email"],
//   "properties": {
//     "name": { "type": "string", "minLength": 1, "maxLength": 100 },
//     "email": { "type": "string", "format": "email" },
//     "status": { "type": "string", "enum": ["lead", "prospect", "customer"], "default": "lead" }
//   }
// }
```

## Security Schemes

Guards are auto-detected and mapped to OpenAPI security schemes:

```json
{
    "securityDefinitions": {
        "bearerAuth": {
            "type": "http",
            "scheme": "bearer",
            "bearerFormat": "JWT"
        }
    }
}
```

## Next Steps

- [HTTP & Routing](http-api.md) -- controller and route attributes
- [Validation](validation.md) -- DTO validation attributes
- [Deployment](deployment.md) -- serving the OpenAPI spec in production
