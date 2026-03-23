---
outline: deep
---

# Validation

LatticePHP validates input through typed DTOs with PHP attributes. When a controller method declares a `#[Body]` parameter, the framework automatically deserializes the request body, validates it against the DTO's attributes, and returns a 422 error if validation fails.

## Basic Usage

Define a DTO with validation attributes on constructor parameters:

```php
<?php
declare(strict_types=1);

namespace App\Modules\Contacts\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateContactDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $name,

        #[Required]
        #[Email]
        public string $email,
    ) {}
}
```

Use it in a controller with `#[Body]`:

```php
#[Post('/')]
public function store(#[Body] CreateContactDto $dto): Response
{
    $contact = Contact::create([
        'name' => $dto->name,
        'email' => $dto->email,
    ]);
    return ResponseFactory::created(['data' => $contact]);
}
```

That's it. If the request body has an invalid email or missing name, the framework returns a 422 before your controller code runs.

## Validation Attributes

### Required Fields

```php
#[Required]
public string $name;
```

The field must be present in the request body and non-null.

### String Constraints

```php
#[StringType]                                    // Must be a string
#[StringType(minLength: 1)]                      // At least 1 character
#[StringType(maxLength: 255)]                    // At most 255 characters
#[StringType(minLength: 8, maxLength: 100)]      // Between 8 and 100 characters
```

### Numeric Constraints

```php
#[IntegerType]                                   // Must be an integer
#[IntegerType(min: 0)]                           // Non-negative integer
#[IntegerType(min: 1, max: 100)]                 // Integer between 1 and 100

#[FloatType]                                     // Must be a float
#[FloatType(min: 0.0, max: 99999.99)]            // Float range
```

### Boolean

```php
#[BooleanType]                                   // Must be true or false
```

### Email

```php
#[Email]                                         // Must be a valid email address
```

### URL

```php
#[Url]                                           // Must be a valid URL
```

### UUID

```php
#[Uuid]                                          // Must be a valid UUID
```

### Date/Time

```php
#[DateTimeType]                                  // Must be a valid date/time string
#[DateTimeType(format: 'Y-m-d')]                 // Must match specific format
```

### Array

```php
#[ArrayType]                                     // Must be an array
```

### Enum Values

```php
#[InArray(values: ['lead', 'prospect', 'customer', 'churned'])]
```

The value must be one of the listed options.

### Nullable Fields

```php
#[Nullable]
public ?string $phone = null;
```

The field may be absent or null. Combine with other attributes for conditional validation:

```php
#[Nullable]
#[StringType(maxLength: 30)]
public ?string $phone = null;
```

If `phone` is provided, it must be a string of at most 30 characters. If absent, the default `null` is used.

### Database Uniqueness

```php
#[Unique(table: 'users', column: 'email')]
```

Checks that no existing row in the specified table has this value.

## Complete Attribute Reference

| Attribute | Parameters | Purpose |
|---|---|---|
| `#[Required]` | -- | Field must be present and non-null |
| `#[Nullable]` | -- | Field may be null or absent |
| `#[StringType]` | `minLength`, `maxLength` | String with optional length constraints |
| `#[IntegerType]` | `min`, `max` | Integer with optional range |
| `#[FloatType]` | `min`, `max` | Float with optional range |
| `#[BooleanType]` | -- | Must be boolean |
| `#[Email]` | -- | Valid email format |
| `#[Url]` | -- | Valid URL format |
| `#[Uuid]` | -- | Valid UUID format |
| `#[DateTimeType]` | `format` | Valid date/time, optional format |
| `#[ArrayType]` | -- | Must be an array |
| `#[InArray]` | `values` | Must be one of the listed values |
| `#[Unique]` | `table`, `column` | Database uniqueness check |

## DTO Styles

### Constructor-Based (Recommended)

Properties are declared as constructor parameters. This is the default pattern used throughout the framework:

```php
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

        #[InArray(values: ['lead', 'prospect', 'customer'])]
        public string $status = 'lead',
    ) {}
}
```

Default values are honored. If `status` is omitted from the request body, it defaults to `'lead'`.

### Property-Based

Properties are declared as public class properties (no constructor). The `DtoMapper` sets them directly:

```php
final class UpdateContactDto
{
    #[Nullable] #[StringType(maxLength: 100)] public ?string $name = null;
    #[Nullable] #[Email] public ?string $email = null;
    #[Nullable] #[StringType(maxLength: 30)] public ?string $phone = null;
}
```

::: tip
Use constructor-based DTOs for creation (where fields are required) and property-based DTOs for updates (where all fields are optional).
:::

## Validation Error Response

When validation fails, the framework returns a **422 Unprocessable Entity** response in [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457) format:

```json
{
    "type": "https://httpstatuses.io/422",
    "title": "Unprocessable Entity",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "email": ["The email field must be a valid email address."],
        "name": ["The name field is required."]
    }
}
```

## Using the ValidationPipe

By default, DTOs annotated with `#[Body]` are validated automatically through the `ParameterResolver`. For explicit pipe-based validation, apply `#[UsePipes]`:

```php
use Lattice\Pipeline\Attributes\UsePipes;
use Lattice\Validation\ValidationPipe;

#[Post('/')]
#[UsePipes(pipes: [ValidationPipe::class])]
public function store(#[Body] CreateContactDto $dto): Response
{
    // dto is guaranteed valid here
}
```

## Testing Validation

```php
public function test_create_contact_validates_email(): void
{
    $response = $this->postJson('/api/contacts', [
        'name' => 'Valid Name',
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
}

public function test_create_contact_requires_name(): void
{
    $response = $this->postJson('/api/contacts', [
        'email' => 'test@example.com',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
}
```

## Next Steps

- [HTTP & Routing](http-api.md) -- controllers, parameter binding, response factories
- [Testing](testing.md) -- testing validation errors with `assertJsonValidationErrors()`
- [Error Handling](error-handling.md) -- customizing error response format
