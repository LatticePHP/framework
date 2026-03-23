---
outline: deep
---

# GraphQL

LatticePHP provides attribute-based GraphQL support through the `lattice/graphql` package. Define your schema using PHP attributes instead of SDL files -- the framework auto-generates the schema from your annotated classes.

## Defining Types

### Object Types

Annotate your response types with `#[ObjectType]` and `#[Field]`:

```php
<?php
declare(strict_types=1);

use Lattice\GraphQL\Attributes\ObjectType;
use Lattice\GraphQL\Attributes\Field;

#[ObjectType]
final class ContactType
{
    #[Field]
    public int $id;

    #[Field]
    public string $name;

    #[Field]
    public string $email;

    #[Field]
    public ?string $phone;

    #[Field]
    public string $status;
}
```

### Input Types

For mutations, define input types:

```php
use Lattice\GraphQL\Attributes\InputType;

#[InputType]
final class CreateContactInput
{
    #[Field]
    public string $name;

    #[Field]
    public string $email;

    #[Field]
    public ?string $phone = null;
}
```

### Enum Types

```php
use Lattice\GraphQL\Attributes\EnumType;

#[EnumType]
enum ContactStatus: string
{
    case Lead = 'lead';
    case Prospect = 'prospect';
    case Customer = 'customer';
    case Churned = 'churned';
}
```

## Defining Queries and Mutations

Use `#[Query]` and `#[Mutation]` on resolver methods:

```php
<?php
declare(strict_types=1);

use Lattice\GraphQL\Attributes\Query;
use Lattice\GraphQL\Attributes\Mutation;
use Lattice\GraphQL\Attributes\Argument;

final class ContactResolver
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    #[Query]
    public function contacts(): array
    {
        return Contact::all()->toArray();
    }

    #[Query]
    public function contact(#[Argument] int $id): ?array
    {
        return Contact::find($id)?->toArray();
    }

    #[Mutation]
    public function createContact(#[Argument] CreateContactInput $input): array
    {
        $contact = $this->service->create($input);
        return $contact->toArray();
    }

    #[Mutation]
    public function deleteContact(#[Argument] int $id): bool
    {
        Contact::findOrFail($id)->delete();
        return true;
    }
}
```

## Module Registration

Register the GraphQL module:

```php
use Lattice\GraphQL\GraphqlModule;

#[Module(
    imports: [GraphqlModule::class],
)]
final class AppModule {}
```

This exposes a `POST /graphql` endpoint that handles all queries and mutations.

## Schema Generation

The `SchemaBuilder` auto-generates the GraphQL schema from your annotated classes at boot time:

- `#[ObjectType]` classes become GraphQL object types
- `#[InputType]` classes become GraphQL input types
- `#[EnumType]` enums become GraphQL enum types
- `#[Query]` methods become root query fields
- `#[Mutation]` methods become root mutation fields
- `#[Field]` properties define the type's fields
- `#[Argument]` parameters define field arguments

The `TypeRegistry` maps PHP types to GraphQL types automatically (`int` -> `Int!`, `?string` -> `String`, `array` -> `[Mixed]`).

## Querying

```bash
# Query
curl -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ contacts { id name email status } }"}'

# Mutation
curl -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { createContact(input: { name: \"Alice\", email: \"alice@test.com\" }) { id name } }"}'
```

## Error Handling

GraphQL errors follow the spec format with the `ErrorFormatter`:

```json
{
    "errors": [
        {
            "message": "Contact not found",
            "locations": [{"line": 1, "column": 3}],
            "path": ["contact"]
        }
    ]
}
```

## Next Steps

- [HTTP & Routing](http-api.md) -- REST API endpoints
- [OpenAPI Generation](openapi.md) -- auto-generate OpenAPI specs
- [Authentication](auth.md) -- protect GraphQL with guards
