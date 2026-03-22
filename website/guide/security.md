---
outline: deep
---

# Security

This guide covers authentication, authorization, encryption, input validation, CORS, rate limiting, and tenant isolation in LatticePHP.

## JWT Authentication

LatticePHP supports both symmetric (HS256) and asymmetric (RS256, ES256) JWT signing.

### Configuration

In `config/auth.php`:

```php
'jwt' => [
    'secret' => env('JWT_SECRET'),                        // Symmetric key (HS256)
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),

    // Asymmetric keys (RS256, ES256)
    'private_key' => env('JWT_PRIVATE_KEY'),
    'public_key' => env('JWT_PUBLIC_KEY'),

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 60),     // 1 hour in minutes
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 10080), // 7 days in minutes

    'issuer' => env('JWT_ISSUER', env('APP_URL')),
    'audience' => env('JWT_AUDIENCE'),
],
```

### Generating Keys

For asymmetric signing (recommended for production):

```bash
# RS256
openssl genrsa -out jwt-private.pem 4096
openssl rsa -in jwt-private.pem -pubout -out jwt-public.pem

# ES256
openssl ecparam -name prime256v1 -genkey -noout -out jwt-private-ec.pem
openssl ec -in jwt-private-ec.pem -pubout -out jwt-public-ec.pem
```

Set `JWT_PRIVATE_KEY` and `JWT_PUBLIC_KEY` to the file paths or base64-encoded contents. Asymmetric keys allow verification services to validate tokens using only the public key.

### Auth Guards

Apply JWT authentication to controllers using `#[UseGuards]`:

```php
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Pipeline\Attributes\UseGuards;

#[Controller('/api/orders')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
final class OrderController { ... }
```

### Multiple Guard Types

LatticePHP supports three guard drivers out of the box:

```php
'guards' => [
    'jwt' => ['driver' => 'jwt', 'provider' => 'users'],
    'pat' => ['driver' => 'pat', 'provider' => 'users'],    // Personal Access Tokens
    'api-key' => ['driver' => 'api-key'],                     // API Key auth
],
```

## Authorization

### Attribute-Based Access Control

Use `#[Authorize]`, `#[Roles]`, and `#[Scopes]` on controllers or individual methods:

```php
use Lattice\Auth\Attributes\Authorize;
use Lattice\Auth\Attributes\Roles;
use Lattice\Auth\Attributes\Scopes;

#[Controller('/api/admin')]
#[Authorize]
#[Roles(roles: ['admin', 'super-admin'])]
final class AdminController
{
    #[Get('/reports')]
    #[Scopes(scopes: ['reports:read'])]
    public function reports(): Response { ... }

    #[Delete('/users/:id')]
    #[Roles(roles: ['super-admin'])]
    public function deleteUser(#[Param] int $id): Response { ... }
}
```

- `#[Authorize]` -- requires any authenticated user
- `#[Roles(roles: ['admin'])]` -- requires the user to have one of the listed roles
- `#[Scopes(scopes: ['read:orders'])]` -- requires the token to have the listed scopes

All three can be applied at the class level (applies to all methods) or method level (overrides class-level).

### Policies

For resource-level authorization, use `#[Policy]` and `#[Can]`:

```php
use Lattice\Auth\Attributes\Policy;
use Lattice\Auth\Attributes\Can;
use Lattice\Auth\Principal;

#[Policy(model: Order::class)]
final class OrderPolicy
{
    public function view(Principal $user, Order $order): bool
    {
        return (int) $user->getId() === $order->user_id;
    }

    public function delete(Principal $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }
}

// On controller method:
#[Get('/:id')]
#[Can('view', Order::class)]
public function show(#[Param] int $id): Response { ... }
```

## Password Hashing

LatticePHP supports bcrypt and argon2id via `HashManager`:

```php
use Lattice\Auth\Facades\Hash;

// Hash a password
$hash = Hash::make('user-password');

// Verify
$valid = Hash::check('user-password', $hash);
```

Configuration in `config/auth.php`:

```php
'hashing' => [
    'driver' => env('HASH_DRIVER', 'bcrypt'),
    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
    ],
    'argon2id' => [
        'memory' => 65536,
        'time' => 4,
        'threads' => 1,
    ],
],
```

## Encryption

The `Encrypter` class provides AES-256-GCM authenticated encryption.

```php
use Lattice\Auth\Encryption\Encrypter;

// Generate a key
$key = Encrypter::generateKey('aes-256-gcm');

$encrypter = new Encrypter($key, 'aes-256-gcm');

// Encrypt (serializes by default)
$encrypted = $encrypter->encrypt(['sensitive' => 'data']);

// Decrypt
$data = $encrypter->decrypt($encrypted); // ['sensitive' => 'data']

// Encrypt without serialization (raw string)
$token = $encrypter->encrypt('raw-value', serialize: false);
$raw = $encrypter->decrypt($token, unserialize: false);
```

GCM mode provides both confidentiality and integrity -- tampered payloads are detected and rejected with an `EncryptionException`.

## CORS Configuration

In `config/cors.php`:

```php
return [
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type', 'Authorization', 'X-Requested-With',
        'X-Workspace-Id', 'X-Tenant-Id', 'Accept', 'Origin',
    ],
    'exposed_headers' => [
        'X-RateLimit-Limit', 'X-RateLimit-Remaining',
        'X-RateLimit-Reset', 'X-Request-Id',
    ],
    'max_age' => (int) env('CORS_MAX_AGE', 86400),
    'supports_credentials' => (bool) env('CORS_CREDENTIALS', false),
];
```

For production, replace `'*'` with specific origins: `CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com`.

## Rate Limiting

Apply rate limits at the controller or method level using `#[RateLimit]`:

```php
use Lattice\RateLimit\Attributes\RateLimit;

#[Controller('/api/auth')]
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
final class AuthController
{
    #[Post('/login')]
    #[RateLimit(maxAttempts: 5, decaySeconds: 300, key: 'login')]
    public function login(#[Body] LoginDto $dto): Response { ... }
}
```

- `maxAttempts` -- maximum requests allowed in the window (default: 60)
- `decaySeconds` -- window duration in seconds (default: 60)
- `key` -- custom rate limit key (defaults to IP + route)

The `RateLimitGuard` enforces limits and returns `429 Too Many Requests` with headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.

## Input Validation via DTOs

All user input is validated through typed DTOs with validation attributes:

```php
final readonly class CreateUserDto
{
    public function __construct(
        #[Required]
        #[Email]
        #[Unique(table: 'users', column: 'email')]
        public string $email,

        #[Required]
        #[StringType(minLength: 8)]
        public string $password,
    ) {}
}
```

DTOs are validated before the controller method runs. Invalid input returns 422 with structured errors.

## Workspace / Tenant Isolation

### Workspace Guard

```php
use Lattice\Auth\Workspace\WorkspaceGuard;

#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController { ... }
```

The `WorkspaceGuard` extracts the workspace from the `X-Workspace-Id` header and validates the user's membership. Models using the `BelongsToWorkspace` trait are automatically scoped.

### Tenant Guard

For full data isolation between tenants:

```php
use Lattice\Auth\Tenancy\TenantGuard;

#[Controller('/api/data')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, TenantGuard::class])]
final class DataController { ... }
```

## Security Checklist

1. Use asymmetric JWT keys (RS256/ES256) in production
2. Set `CORS_ALLOWED_ORIGINS` to specific domains, never `*` in production
3. Enable rate limiting on authentication endpoints
4. Use argon2id for password hashing in high-security environments
5. Rotate encryption keys periodically; `Encrypter::generateKey()` creates new ones
6. Always use DTOs for input validation -- never trust raw request data
7. Apply `#[Authorize]` to every controller that requires authentication
8. Use workspace/tenant guards for multi-tenant data isolation
9. Store secrets in environment variables, never in config files or source code
10. Run `php lattice compile` in production to lock down the compiled manifest
