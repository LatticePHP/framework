---
outline: deep
---

# Authentication and Authorization

## Overview

LatticePHP ships JWT authentication out of the box. The auth system is built on three layers: `AuthServiceProvider` binds the token infrastructure, `JwtAuthenticationGuard` protects routes via the pipeline, and the `Gate` + RBAC traits handle authorization. No session state -- every request carries a Bearer token.

## AuthServiceProvider Bindings

`Lattice\Auth\AuthServiceProvider` registers these singletons in the container:

| Binding | Implementation | Purpose |
|---------|---------------|---------|
| `JwtConfig::class` | `JwtConfig` | Secret, algorithm, TTL from env |
| `JwtEncoder::class` | `JwtEncoder` | Encodes/decodes JWT tokens |
| `RefreshTokenStoreInterface::class` | `InMemoryRefreshTokenStore` | Stores refresh tokens (swap for DB in prod) |
| `TokenIssuerInterface::class` | `JwtTokenIssuer` | Issues and refreshes token pairs |
| `HashManager::class` | `HashManager` | Password hashing (bcrypt default) |
| `JwtAuthenticationGuard::class` | `JwtAuthenticationGuard` | Pipeline guard for Bearer tokens |

Configuration is driven by environment variables:

```php
// AuthServiceProvider binds JwtConfig from env
new JwtConfig(
    secret: $_ENV['JWT_SECRET'] ?? 'change-me',
    algorithm: $_ENV['JWT_ALGORITHM'] ?? 'HS256',
    accessTokenTtl: (int) ($_ENV['JWT_ACCESS_TTL'] ?? 3600),
    refreshTokenTtl: (int) ($_ENV['JWT_REFRESH_TTL'] ?? 604800),
);
```

## JWT Authentication Flow

### 1. Login -- Password Verification and Token Issuance

The `AuthController` handles the full lifecycle. Login verifies the password with `HashManager::check()`, builds a `Principal`, and issues a token pair:

```php
#[Controller('/api/auth')]
final class AuthController
{
    public function __construct(
        private readonly TokenIssuerInterface $issuer,
        private readonly HashManager $hasher,
    ) {}

    #[Post('/login')]
    public function login(#[Body] LoginDto $dto): Response
    {
        $user = User::where('email', $dto->email)->first();

        if ($user === null || !$this->hasher->check($dto->password, $user->password)) {
            return Response::error('Invalid credentials', 401);
        }

        $principal = new Principal(
            id: (string) $user->id,
            type: 'user',
            roles: [$user->role ?? 'user'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal);

        return Response::json([
            'access_token' => $tokenPair->getAccessToken(),
            'refresh_token' => $tokenPair->getRefreshToken(),
            'token_type' => $tokenPair->getTokenType(),
            'expires_in' => $tokenPair->getExpiresIn(),
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }
}
```

### 2. Registration

Registration creates the user (password is hashed by the model mutator) and issues tokens immediately:

```php
#[Post('/register')]
public function register(#[Body] RegisterDto $dto): Response
{
    $user = User::create([
        'name' => $dto->name,
        'email' => $dto->email,
        'password' => $dto->password, // Hashed by model mutator
        'role' => 'user',
    ]);

    $principal = new Principal(id: (string) $user->id, type: 'user', roles: ['user']);
    $tokenPair = $this->issuer->issueAccessToken($principal);

    return Response::json([...], 201);
}
```

The `User` model hashes passwords automatically via a mutator:

```php
public function setPasswordAttribute(string $value): void
{
    $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
}
```

### 3. Token Refresh with Rotation

Refresh tokens are single-use. After refresh, the old token is revoked:

```php
#[Post('/refresh')]
public function refresh(#[Body] RefreshDto $dto): Response
{
    $tokenPair = $this->issuer->refreshAccessToken($dto->refresh_token);
    // Old refresh token is now invalid (rotation)
    return Response::json([
        'access_token' => $tokenPair->getAccessToken(),
        'refresh_token' => $tokenPair->getRefreshToken(),
    ]);
}
```

From the integration test -- reusing a refresh token throws:

```php
$this->issuer->refreshAccessToken($originalPair->getRefreshToken()); // OK
$this->issuer->refreshAccessToken($originalPair->getRefreshToken()); // throws
```

## JwtAuthenticationGuard

`JwtAuthenticationGuard` implements `GuardInterface`. It extracts the Bearer token, decodes it, builds a `Principal`, and sets it on the execution context:

```php
final class JwtAuthenticationGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        $token = $context->getRequest()->bearerToken();
        if ($token === null) return false;

        $claims = $this->encoder->decode($token, $this->config->secret, $this->config->algorithm);

        $principal = new Principal(
            id: $claims['sub'] ?? '',
            type: 'user',
            roles: $claims['roles'] ?? [],
            scopes: $claims['scopes'] ?? [],
            claims: $claims,
        );

        $context->setPrincipal($principal);
        return true;
    }
}
```

No token, invalid token, or expired token all return `false` -- the pipeline throws `ForbiddenException` (403).

## Protecting Routes

Apply `#[UseGuards]` to require authentication on a controller method:

```php
#[Get('/me')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
public function me(#[CurrentUser] Principal $user): Response
{
    $dbUser = User::find($user->getId());
    return Response::json(['data' => [
        'id' => $dbUser->id,
        'name' => $dbUser->name,
        'email' => $dbUser->email,
    ]]);
}
```

`#[CurrentUser]` injects the `Principal` that was set by the guard. Without a valid token, the guard denies and the request never reaches the controller.

## The Principal Object

`Lattice\Auth\Principal` implements `PrincipalInterface` and carries identity through the request:

```php
$principal->getId();       // '42'
$principal->getType();     // 'user'
$principal->getRoles();    // ['admin']
$principal->getScopes();   // ['read', 'write']
$principal->hasRole('admin');   // true
$principal->hasScope('read');   // true
$principal->getClaim('iss');    // 'lattice-test'
```

## RBAC: Roles and Permissions

The `User` model uses two traits for role-based access control:

### HasRoles

```php
$user->assignRole('editor');       // Attach role by slug
$user->removeRole('editor');       // Detach role
$user->hasRole('admin');           // Check by slug
$user->getRoleNames();             // ['admin', 'editor']
$user->roles;                      // BelongsToMany relation via user_roles table
```

### HasPermissions

Checks direct permissions AND permissions inherited through roles:

```php
$user->givePermission('contacts.create');
$user->revokePermission('contacts.create');
$user->hasPermission('contacts.create');  // Checks direct + role permissions
$user->can('contacts.create');            // Same, with super-admin bypass
```

Super-admin bypass: any user with the `super-admin` role passes all `can()` checks.

## Gate: Ability-Based Authorization

`Lattice\Authorization\Gate` defines and checks abilities:

```php
$gate = new Gate();

$gate->define('edit-contact', function (PrincipalInterface $user, Contact $contact) {
    return $user->getId() === $contact->owner_id;
});

$gate->before(function (PrincipalInterface $user) {
    if ($user->hasRole('super-admin')) return true;
    return null; // continue to normal check
});

$gate->allows($principal, 'edit-contact', $contact);   // bool
$gate->denies($principal, 'edit-contact', $contact);    // bool
$gate->authorize($principal, 'edit-contact', $contact); // throws ForbiddenException
```

Scoped gates for a specific user:

```php
$userGate = $gate->forUser($principal);
$userGate->allows(null, 'edit-contact', $contact); // uses the scoped principal
```

## Multi-Guard Authentication

LatticePHP supports multiple guard types on the same route. Stack them in `#[UseGuards]`:

```php
#[UseGuards(guards: [JwtAuthenticationGuard::class])]     // JWT only
#[UseGuards(guards: [PatAuthenticationGuard::class])]      // PAT only
#[UseGuards(guards: [ApiKeyGuard::class])]                 // API key only
```

Each guard implements the same `GuardInterface::canActivate()` contract. The pipeline runs guards in order -- first failure stops the chain.

## Password Hashing

`HashManager` defaults to bcrypt. The `HasherInterface` contract supports multiple drivers:

```php
$hasher = new HashManager();
$hash = $hasher->make('secret123');
$hasher->check('secret123', $hash);  // true
$hasher->check('wrong', $hash);      // false
```

Each call to `make()` produces a different hash (unique salt). The `User` model mutator uses `PASSWORD_BCRYPT` with cost 12.
