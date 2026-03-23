---
outline: deep
---

# API Keys & Personal Access Tokens

LatticePHP supports three authentication methods beyond JWT: API keys, personal access tokens (PATs), and multi-guard stacking.

## API Key Authentication

API keys are long-lived tokens for machine-to-machine communication. They are managed by the `lattice/api-key` package.

### Creating API Keys

```php
use Lattice\ApiKey\ApiKeyManager;

$manager = new ApiKeyManager($store);

$result = $manager->create(
    name: 'CI/CD Pipeline',
    scopes: ['deploy', 'read'],
    expiresAt: new \DateTimeImmutable('+1 year'),
);

// $result->key     -- the raw key (shown once, cannot be retrieved later)
// $result->stored  -- the stored record (hashed key)
```

::: warning
The raw API key is returned only at creation time. Store it securely. It cannot be retrieved from the database later -- only a hash is stored.
:::

### Protecting Routes with API Keys

```php
use Lattice\ApiKey\ApiKeyAuthGuard;
use Lattice\Pipeline\Attributes\UseGuards;

#[Controller('/api/webhooks')]
#[UseGuards(guards: [ApiKeyAuthGuard::class])]
final class WebhookController
{
    #[Post('/deploy')]
    public function deploy(#[CurrentUser] ApiKeyPrincipal $key): Response
    {
        // $key->getId()      -- the key ID
        // $key->getScopes()  -- ['deploy', 'read']
        return ResponseFactory::json(['status' => 'deploying']);
    }
}
```

The client sends the key in the `Authorization` header:

```bash
curl -H "Authorization: Bearer lk_abc123..." https://api.example.com/api/webhooks/deploy
```

## Personal Access Tokens (PATs)

PATs are user-scoped tokens for API access. They're managed by the `lattice/pat` package.

### Issuing PATs

```php
use Lattice\Pat\PatManager;

$manager = new PatManager($store);

$result = $manager->create(
    userId: 42,
    name: 'My CLI Tool',
    scopes: ['read', 'write'],
    expiresAt: new \DateTimeImmutable('+90 days'),
);

// $result->token -- the raw PAT (shown once)
```

### Protecting Routes with PATs

```php
use Lattice\Pat\PatAuthGuard;

#[Controller('/api/user')]
#[UseGuards(guards: [PatAuthGuard::class])]
final class UserApiController
{
    #[Get('/repos')]
    public function repos(#[CurrentUser] Principal $user): Response
    {
        // $user->getId() -- the user ID who owns the PAT
    }
}
```

## Multi-Guard Authentication

Stack multiple guards on a single route. The pipeline tries each guard in order -- the first one that succeeds sets the principal:

```php
// Accept JWT OR API key OR PAT
#[UseGuards(guards: [
    JwtAuthenticationGuard::class,
    ApiKeyAuthGuard::class,
    PatAuthGuard::class,
])]
```

Or configure different guards for different controllers:

```php
// User-facing API: JWT only
#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
final class ContactController { /* ... */ }

// Webhook receiver: API key only
#[Controller('/api/webhooks')]
#[UseGuards(guards: [ApiKeyAuthGuard::class])]
final class WebhookController { /* ... */ }

// CLI/automation: PAT only
#[Controller('/api/cli')]
#[UseGuards(guards: [PatAuthGuard::class])]
final class CliController { /* ... */ }
```

## Guard Configuration

Configure available guards in `config/auth.php`:

```php
'guards' => [
    'jwt'     => ['driver' => 'jwt', 'provider' => 'users'],
    'pat'     => ['driver' => 'pat', 'provider' => 'users'],
    'api-key' => ['driver' => 'api-key'],
],
```

## Testing

Both API key and PAT packages provide in-memory stores:

```php
use Lattice\ApiKey\InMemoryApiKeyStore;
use Lattice\Pat\InMemoryPatStore;

// In tests, use in-memory stores
$container->instance(ApiKeyStoreInterface::class, new InMemoryApiKeyStore());
$container->instance(PatStoreInterface::class, new InMemoryPatStore());
```

## Next Steps

- [Authentication](auth.md) -- JWT authentication
- [OAuth2 Server](oauth.md) -- full OAuth2 authorization
- [Authorization](authorization.md) -- roles, scopes, and policies
