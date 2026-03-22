# 10 — OAuth & Social Auth

> Complete the OAuth2 server (authorization code + PKCE, token management, JWT, scopes, client CLI) and social auth (GitHub, Google, user linking)

## Dependencies
- Wave 1-3 packages complete
- Packages: `packages/oauth/`, `packages/social/`, `packages/auth/`, `packages/jwt/`, `packages/http/`, `packages/database/`, `packages/http-client/`, `packages/routing/`

## Subtasks

### 1. [ ] OAuth2 authorization code + PKCE flow
- Implement authorization endpoint (`/oauth/authorize`) with consent screen rendering and scope display
- Implement authorization code generation and storage with short-lived expiry (10 minutes)
- Implement PKCE challenge/verifier validation per RFC 7636
- Implement token endpoint (`/oauth/token`) for authorization code exchange
- Enforce `code_verifier` requirement when `code_challenge` was sent
- Implement redirect URI validation (exact string match)
- Prevent authorization code reuse — invalidate code after first exchange
- Write tests for the full authorize -> consent -> token flow with PKCE

#### Detailed Steps
1. Create `AuthorizationController` handling `GET /oauth/authorize` with `response_type`, `client_id`, `redirect_uri`, `scope`, `state`, `code_challenge`, `code_challenge_method` params
2. Create consent screen view that displays requested scopes and client info
3. Create `AuthorizationCode` model with fields: code, client_id, user_id, scopes, redirect_uri, code_challenge, code_challenge_method, expires_at, used_at
4. On consent approval, generate a cryptographically random authorization code, store with associated data, redirect to `redirect_uri` with code and state
5. Create `TokenController` handling `POST /oauth/token` with `grant_type=authorization_code`, `code`, `redirect_uri`, `client_id`, `code_verifier`
6. Validate code_verifier against stored code_challenge using S256 method (SHA-256 of verifier must match challenge)
7. Exchange code for access token + refresh token, mark code as used
8. Return JSON response: `{ "access_token": "...", "token_type": "Bearer", "expires_in": 3600, "refresh_token": "...", "scope": "..." }`

#### Verification
- [ ] `GET /oauth/authorize` renders consent screen with scopes
- [ ] Authorization code is generated, stored, and expires after 10 minutes
- [ ] PKCE S256 validation rejects invalid code_verifier
- [ ] Code reuse returns error on second attempt
- [ ] Token endpoint returns access + refresh tokens on valid exchange

### 2. [ ] Token introspection + revocation endpoints
- Implement token introspection endpoint (`/oauth/introspect`) per RFC 7662
- Implement token revocation endpoint (`/oauth/revoke`) per RFC 7009
- Support revoking both access tokens and refresh tokens
- Cascade revocation: revoking a refresh token also revokes all derived access tokens
- Implement refresh token rotation — issue new refresh token on each use
- Detect and invalidate refresh token reuse (token family revocation)
- Write tests for introspection with valid, expired, and revoked tokens
- Write tests for revocation and refresh token rotation

#### Detailed Steps
1. Create `POST /oauth/introspect` endpoint accepting `token` and `token_type_hint` params
2. Respond with RFC 7662 format: `{ "active": true/false, "scope": "...", "client_id": "...", "username": "...", "exp": ..., "iat": ... }`
3. Create `POST /oauth/revoke` endpoint accepting `token` and `token_type_hint` params
4. Always return 200 on revocation (even if token was already invalid) per RFC 7009
5. Implement refresh token family tracking: each refresh token stores a `family_id`
6. On refresh, generate a new refresh token in the same family, invalidate the old one
7. If a previously-used refresh token is presented (reuse attempt), revoke the entire family

#### Verification
- [ ] Introspection returns `active: true` for valid tokens and `active: false` for expired/revoked
- [ ] Revocation invalidates the specified token immediately
- [ ] Refresh token rotation issues a new refresh token and invalidates the old
- [ ] Reuse of an old refresh token revokes the entire token family

### 3. [ ] JWT access tokens + scope management
- Implement JWT access token generation using `lattice/jwt`
- Include standard claims: `sub`, `iss`, `aud`, `exp`, `iat`, `jti`
- Include `scope` claim and support custom claims via configurable callback
- Implement JWT validation (signature, expiry, audience)
- Support RSA and EC key pairs for signing
- Publish JWKS endpoint (`/.well-known/jwks.json`)
- Define scope registry with configurable available scopes
- Validate requested scopes against client's allowed scopes and the registry
- Enforce scopes on API routes via middleware
- Support default scopes when none are requested
- Write tests for JWT generation, validation, and scope enforcement

#### Detailed Steps
1. Create `JwtAccessTokenGenerator` that builds JWT with standard claims using `lattice/jwt`
2. Create `config/oauth.php` with key paths (RSA/EC), issuer, audience, and custom claims callback
3. Create `JwksController` serving `GET /.well-known/jwks.json` with public key(s) in JWK format
4. Create `ScopeRegistry` class with `register(string $scope, string $description)`, `validate(array $requested, array $allowed): array`
5. Create `CheckScopes` middleware that reads JWT scope claim and validates against route requirements
6. Support `#[RequireScope('read:users')]` attribute on controllers/routes

#### Verification
- [ ] JWT contains all standard claims and custom claims
- [ ] JWKS endpoint returns valid JWK set matching signing key
- [ ] Scope middleware rejects requests with insufficient scopes
- [ ] Default scopes are applied when client requests none

### 4. [ ] Client management CLI commands
- Implement `php lattice oauth:client:create` — interactive client creation
- Implement `php lattice oauth:client:list` — table of all registered clients
- Implement `php lattice oauth:client:delete` — remove a client by ID
- Implement `php lattice oauth:client:update` — modify redirect URIs, scopes
- Support confidential and public client types
- Store client secrets hashed with bcrypt
- Write tests for all CLI commands

#### Detailed Steps
1. Create `OAuthClient` model with fields: id, name, secret_hash, redirect_uris (JSON), scopes (JSON), type (confidential/public), created_at, updated_at
2. Create migration for `oauth_clients` table
3. Implement `CreateClientCommand` that prompts for name, type, redirect URIs, scopes; generates client_id and secret; displays secret once
4. Implement `ListClientsCommand` with formatted table output (id, name, type, redirect_uris, created_at)
5. Implement `DeleteClientCommand` with confirmation prompt
6. Implement `UpdateClientCommand` for modifying redirect_uris and allowed scopes

#### Verification
- [ ] `oauth:client:create` generates a new client with hashed secret
- [ ] `oauth:client:list` displays all clients in a formatted table
- [ ] `oauth:client:delete` removes the client after confirmation
- [ ] `oauth:client:update` modifies redirect URIs and scopes

### 5. [ ] Social auth provider interface + GitHub provider
- Define `SocialProviderInterface` with `redirect()`, `callback()`, `user()` methods
- Implement `AbstractSocialProvider` base class with shared logic (state generation, CSRF validation, configurable scopes)
- Define `SocialUser` value object (id, name, email, avatar, raw profile)
- Implement `GitHubProvider` extending the abstract provider
- Implement redirect URL construction with correct GitHub OAuth endpoints
- Implement authorization code exchange for access token
- Implement user profile fetch from GitHub API (`/user`) and email fetch (`/user/emails`) when not public
- Map GitHub profile to `SocialUser`
- Write tests using mock HTTP responses

#### Detailed Steps
1. Define `SocialProviderInterface` with methods: `redirect(): RedirectResponse`, `callback(Request $request): SocialUser`, `user(string $accessToken): SocialUser`
2. Create `AbstractSocialProvider` with: state generation (random string stored in session), state validation on callback, scope management, OAuth HTTP flow helpers
3. Create `SocialUser` value object with: id, name, nickname, email, avatar, provider, raw (full profile array)
4. Create `GitHubProvider` with `authorizeUrl = https://github.com/login/oauth/authorize`, `tokenUrl = https://github.com/login/oauth/access_token`
5. Implement `GET /user` for profile and `GET /user/emails` for primary email when not public
6. Map: `login` -> nickname, `name` -> name, `avatar_url` -> avatar, primary verified email -> email

#### Verification
- [ ] `GitHubProvider::redirect()` returns a redirect to GitHub with correct params and state
- [ ] `GitHubProvider::callback()` exchanges code for token and returns `SocialUser`
- [ ] Email is fetched from `/user/emails` when not publicly available
- [ ] State parameter validation prevents CSRF attacks

### 6. [ ] Google provider + user creation/linking
- Implement `GoogleProvider` extending the abstract provider
- Implement redirect URL construction with Google OAuth endpoints
- Implement authorization code exchange for access token
- Implement user profile fetch from userinfo endpoint and ID token parsing alternative
- Map Google profile to `SocialUser`
- Implement user creation from `SocialUser` data
- Implement user linking (connect social account to existing user by email match)
- Handle duplicate email conflicts
- Store social provider ID and provider name for future logins
- Support unlinking a social account and multiple social accounts per user
- Register callback route (`/auth/{provider}/callback`) with state validation
- Handle authorization denied and provider errors
- Authenticate user after successful callback and redirect to configurable post-login URL
- Fire events: `SocialLogin`, `SocialAccountLinked`, `SocialAccountCreated`
- Write tests for creation, linking, conflict scenarios, and the full redirect -> callback -> auth flow

#### Detailed Steps
1. Create `GoogleProvider` with `authorizeUrl = https://accounts.google.com/o/oauth2/v2/auth`, `tokenUrl = https://oauth2.googleapis.com/token`
2. Implement userinfo fetch from `https://www.googleapis.com/oauth2/v3/userinfo`
3. Implement ID token parsing as alternative (decode JWT from token response, extract claims)
4. Map: `sub` -> id, `name` -> name, `email` -> email, `picture` -> avatar
5. Create `social_accounts` table: id, user_id, provider, provider_user_id, access_token, refresh_token, created_at, updated_at
6. Create `SocialAccountManager` with: `findOrCreate(SocialUser $socialUser, string $provider): User`, `link(User $user, SocialUser $socialUser, string $provider): void`, `unlink(User $user, string $provider): void`
7. In `findOrCreate`: check for existing social account by provider + provider_user_id; if not found, check for existing user by email; if not found, create new user
8. Create `SocialAuthController` handling `GET /auth/{provider}/callback` — validate state, exchange code, findOrCreate user, authenticate, redirect
9. Fire appropriate events at each step in the callback flow

#### Verification
- [ ] `GoogleProvider::redirect()` returns a redirect to Google with correct params
- [ ] `GoogleProvider::callback()` returns a valid `SocialUser` from Google profile
- [ ] New user is created when no matching email exists
- [ ] Existing user is linked when email matches
- [ ] Multiple social accounts can be linked to one user
- [ ] Events fire correctly during the callback flow

### 7. [ ] Documentation + tests
- Create `FakeSocialProvider` for testing without real HTTP calls
- Create `FakeOAuthClient` for testing OAuth flows in feature tests
- Document OAuth2 authorization code + PKCE flow with sequence diagrams
- Document social auth setup (GitHub, Google) with step-by-step config
- Document token management (introspection, revocation, rotation)
- Document testing patterns (using fakes, mocking providers)
- Add examples to docs/guides/

#### Detailed Steps
1. Create `FakeSocialProvider` implementing `SocialProviderInterface` that returns configurable `SocialUser` without HTTP calls
2. Create `FakeOAuthClient` that simulates the full authorize -> token flow in tests without HTTP
3. Write sequence diagrams: Authorization Code + PKCE flow, Social Auth redirect -> callback -> user creation
4. Write configuration guide: GitHub App setup, Google OAuth Console setup, env variables
5. Write testing guide: how to use fakes, how to test protected routes with scoped tokens

#### Verification
- [ ] `FakeSocialProvider` is usable in tests without any HTTP calls
- [ ] `FakeOAuthClient` simulates the full OAuth flow in feature tests
- [ ] Documentation covers setup, usage, and testing for all features

## Integration Verification
- [ ] Full authorization code + PKCE flow works end-to-end: authorize -> consent -> code -> token -> introspect
- [ ] Token introspection returns correct active status for valid, expired, and revoked tokens
- [ ] Refresh token rotation issues new tokens and detects reuse
- [ ] Social login with GitHub creates a new user and authenticates
- [ ] Social login with Google links to an existing user by email match
- [ ] Scoped JWT tokens are enforced on protected API routes
- [ ] CLI commands manage OAuth clients correctly
