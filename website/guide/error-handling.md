---
outline: deep
---

# Error Handling

LatticePHP converts all exceptions into structured JSON responses following the [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457) specification. Every error response has a consistent shape, whether it's a validation failure, a missing resource, or an internal server error.

## Default Error Format

All errors follow this structure:

```json
{
    "type": "https://httpstatuses.io/404",
    "title": "Not Found",
    "status": 404,
    "detail": "No query results for model [Contact]."
}
```

For validation errors, an `errors` object is included:

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

## Built-in Exception Mapping

The `ExceptionHandler` maps common exceptions to HTTP status codes:

| Exception | Status | Title |
|---|---|---|
| `ModelNotFoundException` | 404 | Not Found |
| `ValidationException` | 422 | Unprocessable Entity |
| `UnauthorizedException` | 401 | Unauthorized |
| `ForbiddenException` | 403 | Forbidden |
| `BadRequestException` | 400 | Bad Request |
| `RuntimeException` | 500 | Internal Server Error |

::: tip
Eloquent's `findOrFail()` throws `ModelNotFoundException`, which automatically becomes a 404 response. You don't need to catch it yourself.
:::

## The ProblemDetailsFilter

`ProblemDetailsFilter` is the default exception filter applied to every route by the `HttpKernel`. It catches any unhandled exception and converts it to a Problem Details response:

```php
// This is applied automatically -- you don't need to do anything
// HttpKernel appends ProblemDetailsFilter as the last filter on every route
```

For non-production environments (`APP_DEBUG=true`), the response includes additional debugging information like the exception class and stack trace.

## ResponseFactory Error Methods

Use `ResponseFactory` to return structured errors from your controllers:

```php
use Lattice\Http\ResponseFactory;

// 404 Not Found
ResponseFactory::error('Resource not found', 404);

// 422 Validation Error
ResponseFactory::validationError([
    'email' => ['The email is already taken.'],
]);

// 429 Too Many Requests with rate limit headers
ResponseFactory::tooManyRequests(limit: 100, reset: time() + 60);
```

## Custom Exception Filters

Create a custom filter to handle specific exception types:

```php
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;

final class PaymentExceptionFilter implements ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
    {
        if ($exception instanceof PaymentDeclinedException) {
            return ResponseFactory::json([
                'type' => 'https://api.example.com/errors/payment-declined',
                'title' => 'Payment Declined',
                'status' => 402,
                'detail' => $exception->getMessage(),
                'decline_code' => $exception->getDeclineCode(),
            ], 402);
        }

        throw $exception; // Re-throw for the next filter to handle
    }
}
```

Apply it to a controller:

```php
use Lattice\Pipeline\Attributes\UseFilters;

#[Controller('/api/payments')]
#[UseFilters(filters: [PaymentExceptionFilter::class])]
final class PaymentController { /* ... */ }
```

## Error Recovery

The framework handles errors gracefully. After a 500 error, subsequent requests continue working normally. The pipeline catches exceptions at the handler level and does not corrupt shared state:

```php
// Request 1: throws RuntimeException -> 500 response
// Request 2: works normally -> 200 response
// Request 3: works normally -> 200 response
```

This is verified in integration tests with 6 sequential requests including intentional failures.

## Testing Error Responses

```php
public function test_missing_contact_returns_404(): void
{
    $response = $this->getJson('/api/contacts/99999');
    $response->assertNotFound();
    $response->assertJson(['status' => 404]);
}

public function test_invalid_input_returns_422(): void
{
    $response = $this->postJson('/api/contacts', [
        'email' => 'not-an-email',
    ]);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email', 'name']);
}

public function test_unauthorized_returns_401(): void
{
    // No token
    $response = $this->getJson('/api/protected');
    $response->assertUnauthorized();
}
```

## Next Steps

- [Validation](validation.md) -- DTO validation and 422 error responses
- [Pipeline](pipeline.md) -- how exception filters fit into the request pipeline
- [Security](security.md) -- authentication and authorization errors
