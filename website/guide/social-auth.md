---
outline: deep
---

# Social Auth

LatticePHP provides social authentication via the `lattice/social` package, allowing users to sign in with GitHub, Google, or any generic OAuth2 provider.

## Available Providers

| Provider | Class | Status |
|---|---|---|
| GitHub | `GitHubProvider` | Built-in |
| Google | `GoogleProvider` | Built-in |
| Custom OAuth2 | `GenericOAuthProvider` | Configurable |

## Configuration

Register the social auth module and configure providers:

```php
use Lattice\Social\SocialAuthManager;

$social = new SocialAuthManager();

$social->registerProvider('github', new GitHubProvider(
    clientId: env('GITHUB_CLIENT_ID'),
    clientSecret: env('GITHUB_CLIENT_SECRET'),
    redirectUri: env('APP_URL') . '/auth/github/callback',
));

$social->registerProvider('google', new GoogleProvider(
    clientId: env('GOOGLE_CLIENT_ID'),
    clientSecret: env('GOOGLE_CLIENT_SECRET'),
    redirectUri: env('APP_URL') . '/auth/google/callback',
));
```

## Authentication Flow

### Step 1: Redirect to Provider

```php
#[Get('/auth/:provider')]
public function redirect(#[Param] string $provider): Response
{
    $url = $this->social->getRedirectUrl($provider);
    return ResponseFactory::json(['redirect_url' => $url]);
}
```

### Step 2: Handle Callback

```php
#[Get('/auth/:provider/callback')]
public function callback(#[Param] string $provider, Request $request): Response
{
    $code = $request->getQuery('code');
    $socialUser = $this->social->handleCallback($provider, $code);

    // Find or create the local user
    $user = User::firstOrCreate(
        ['email' => $socialUser->getEmail()],
        ['name' => $socialUser->getName()],
    );

    // Link the social identity
    $this->identityStore->link($user->id, $provider, $socialUser->getId());

    // Issue JWT tokens
    $tokenPair = $this->issuer->issueAccessToken(
        new Principal(id: (string) $user->id, type: 'user'),
    );

    return ResponseFactory::json([
        'access_token' => $tokenPair->getAccessToken(),
        'user' => ['id' => $user->id, 'name' => $user->name],
    ]);
}
```

## Identity Linking

The `IdentityLinkStore` tracks which social accounts are linked to local users:

```php
// Link a social identity
$store->link(userId: 42, provider: 'github', providerUserId: 'gh_12345');

// Find a user by social identity
$userId = $store->findByProvider('github', 'gh_12345');

// Unlink
$store->unlink(userId: 42, provider: 'github');
```

## Generic OAuth2 Provider

Connect to any OAuth2-compatible service:

```php
$social->registerProvider('gitlab', new GenericOAuthProvider(
    clientId: env('GITLAB_CLIENT_ID'),
    clientSecret: env('GITLAB_CLIENT_SECRET'),
    redirectUri: env('APP_URL') . '/auth/gitlab/callback',
    authorizationUrl: 'https://gitlab.com/oauth/authorize',
    tokenUrl: 'https://gitlab.com/oauth/token',
    userInfoUrl: 'https://gitlab.com/api/v4/user',
    scopes: ['read_user'],
));
```

## Testing

Use `FakeSocialProvider` in tests:

```php
use Lattice\Social\Testing\FakeSocialProvider;

$fake = new FakeSocialProvider(
    id: 'fake-github-id',
    name: 'Test User',
    email: 'test@example.com',
);

$social->registerProvider('github', $fake);
```

## Next Steps

- [OAuth2 Server](oauth.md) -- full OAuth2 authorization server
- [API Keys & PATs](api-keys.md) -- alternative authentication methods
- [Authentication](auth.md) -- JWT-based authentication
