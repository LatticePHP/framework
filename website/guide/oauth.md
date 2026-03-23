---
outline: deep
---

# OAuth2 Server

LatticePHP includes a full OAuth2 authorization server with support for Authorization Code (with PKCE), Client Credentials, and Refresh Token grants.

## Overview

The OAuth2 server is implemented in the `lattice/oauth` package. It provides:

- **Authorization Code Grant** -- for user-facing applications (SPAs, mobile apps)
- **Client Credentials Grant** -- for machine-to-machine communication
- **Refresh Token Grant** -- for renewing access tokens
- **PKCE validation** -- protects against authorization code interception
- **Token introspection** -- verify token validity
- **Token revocation** -- invalidate tokens

## Installation

The OAuth package is included in the framework. Register the module in your application:

```php
use Lattice\OAuth\OAuthModule;

#[Module(
    imports: [OAuthModule::class],
)]
final class AppModule {}
```

## Registering Clients

Use the `ClientRepository` to register OAuth clients:

```php
use Lattice\OAuth\OAuthClient;

$client = new OAuthClient(
    id: 'my-spa',
    secret: 'client-secret-here',           // null for public clients (SPAs)
    name: 'My SPA Application',
    redirectUris: ['https://app.example.com/callback'],
    grantTypes: ['authorization_code', 'refresh_token'],
    scopes: ['read', 'write'],
);
```

::: tip
SPAs and mobile apps are **public clients** and should not have a client secret. Use PKCE instead to secure the authorization code flow.
:::

## Authorization Code Grant

### Step 1: Authorization Request

Redirect the user to the authorization endpoint:

```
GET /oauth/authorize?
    response_type=code
    &client_id=my-spa
    &redirect_uri=https://app.example.com/callback
    &scope=read write
    &state=random-csrf-token
    &code_challenge=BASE64URL(SHA256(code_verifier))
    &code_challenge_method=S256
```

### Step 2: User Approves

The `AuthorizationController` handles the consent screen and redirects back with a code:

```
https://app.example.com/callback?code=AUTH_CODE&state=random-csrf-token
```

### Step 3: Exchange Code for Tokens

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&code=AUTH_CODE
&redirect_uri=https://app.example.com/callback
&client_id=my-spa
&code_verifier=ORIGINAL_CODE_VERIFIER
```

Response:

```json
{
    "access_token": "eyJhbG...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "refresh_token": "dGhpcyBpcyBh...",
    "scope": "read write"
}
```

## Client Credentials Grant

For server-to-server communication without a user:

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=my-service
&client_secret=service-secret
&scope=api:read
```

## Refresh Token Grant

Exchange a refresh token for new tokens:

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token
&refresh_token=dGhpcyBpcyBh...
&client_id=my-spa
```

## PKCE (Proof Key for Code Exchange)

PKCE prevents authorization code interception attacks. It's required for public clients:

```php
use Lattice\OAuth\PkceValidator;

// The validator checks:
// 1. code_challenge was stored during authorization
// 2. code_verifier hashes to the stored challenge
// 3. Method is S256 (SHA-256)
```

## Token Introspection

Verify whether a token is active:

```bash
POST /oauth/introspect
Content-Type: application/x-www-form-urlencoded

token=eyJhbG...
&token_type_hint=access_token
```

## Token Revocation

Invalidate a token:

```bash
POST /oauth/revoke
Content-Type: application/x-www-form-urlencoded

token=eyJhbG...
&token_type_hint=access_token
```

## Scopes

Register scopes with the `ScopeRegistry`:

```php
use Lattice\OAuth\ScopeRegistry;

$scopes = new ScopeRegistry();
$scopes->register('read', 'Read access to resources');
$scopes->register('write', 'Write access to resources');
$scopes->register('admin', 'Administrative access');
```

Protect controller methods with `#[Scopes]`:

```php
#[Get('/admin/users')]
#[Scopes(scopes: ['admin'])]
public function listUsers(): Response { /* ... */ }
```

## Endpoints Summary

| Endpoint | Method | Purpose |
|---|---|---|
| `/oauth/authorize` | GET | Authorization page (user consent) |
| `/oauth/token` | POST | Exchange credentials for tokens |
| `/oauth/introspect` | POST | Verify token validity |
| `/oauth/revoke` | POST | Revoke a token |

## Next Steps

- [Social Auth](social-auth.md) -- GitHub, Google, and generic OAuth providers
- [API Keys & PATs](api-keys.md) -- alternative authentication methods
- [Authentication](auth.md) -- JWT authentication
