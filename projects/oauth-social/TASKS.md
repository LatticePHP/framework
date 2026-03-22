# OAuth & Social Auth — Task List

## OAuth2 Server: Authorization Code + PKCE

- [ ] Implement authorization endpoint (`/oauth/authorize`)
- [ ] Implement consent screen rendering with scope display
- [ ] Implement authorization code generation and storage
- [ ] Implement PKCE challenge/verifier validation (RFC 7636)
- [ ] Implement token endpoint for authorization code exchange (`/oauth/token`)
- [ ] Enforce `code_verifier` requirement when `code_challenge` was sent
- [ ] Implement redirect URI validation (exact match)
- [ ] Set authorization code expiry (short-lived, e.g., 10 minutes)
- [ ] Prevent authorization code reuse
- [ ] Write tests for the full authorize -> token flow

## OAuth2 Server: Token Management

- [ ] Implement token introspection endpoint (`/oauth/introspect`, RFC 7662)
- [ ] Implement token revocation endpoint (`/oauth/revoke`, RFC 7009)
- [ ] Support revoking access tokens
- [ ] Support revoking refresh tokens (and cascade to access tokens)
- [ ] Implement refresh token rotation (issue new refresh token on each use)
- [ ] Detect and invalidate refresh token reuse (token family revocation)
- [ ] Write tests for introspection with valid/expired/revoked tokens
- [ ] Write tests for revocation

## OAuth2 Server: JWT Access Tokens

- [ ] Implement JWT access token generation using `lattice/jwt`
- [ ] Include standard claims (sub, iss, aud, exp, iat, jti)
- [ ] Include scope claim
- [ ] Include custom claims via configurable callback
- [ ] Implement JWT access token validation (signature, expiry, audience)
- [ ] Support RSA and EC key pairs for signing
- [ ] Publish JWKS endpoint (`/.well-known/jwks.json`)
- [ ] Write tests for token generation and validation

## OAuth2 Server: Scope Management

- [ ] Define scope registry (configurable list of available scopes)
- [ ] Validate requested scopes against client's allowed scopes
- [ ] Validate requested scopes against the registry
- [ ] Enforce scopes on API routes via middleware
- [ ] Support default scopes when none are requested
- [ ] Write tests for scope validation and enforcement

## OAuth2 Server: Client Management

- [ ] Implement `oauth:client:create` CLI command
- [ ] Implement `oauth:client:list` CLI command
- [ ] Implement `oauth:client:delete` CLI command
- [ ] Implement `oauth:client:update` CLI command (redirect URIs, scopes)
- [ ] Support confidential and public client types
- [ ] Store client secrets hashed (bcrypt)
- [ ] Write tests for CLI commands

## Social Auth: Provider Interface

- [ ] Define `SocialProviderInterface` with `redirect()`, `callback()`, `user()` methods
- [ ] Implement `AbstractSocialProvider` base class with shared logic
- [ ] Implement state parameter generation and validation (CSRF protection)
- [ ] Implement configurable scopes per provider
- [ ] Define `SocialUser` value object (id, name, email, avatar, raw profile)

## Social Auth: GitHub Provider

- [ ] Implement `GitHubProvider` extending the abstract provider
- [ ] Implement redirect URL construction with correct OAuth endpoints
- [ ] Implement authorization code exchange for access token
- [ ] Implement user profile fetch from GitHub API (`/user`)
- [ ] Implement email fetch from GitHub API (`/user/emails`) when not public
- [ ] Map GitHub profile to `SocialUser`
- [ ] Write tests using mock HTTP responses

## Social Auth: Google Provider

- [ ] Implement `GoogleProvider` extending the abstract provider
- [ ] Implement redirect URL construction with Google OAuth endpoints
- [ ] Implement authorization code exchange for access token
- [ ] Implement user profile fetch from Google API (userinfo endpoint)
- [ ] Implement ID token parsing as alternative to userinfo call
- [ ] Map Google profile to `SocialUser`
- [ ] Write tests using mock HTTP responses

## Social Auth: User Handling

- [ ] Implement user creation from `SocialUser` data
- [ ] Implement user linking (connect social account to existing user by email)
- [ ] Handle duplicate email conflicts (social provider email matches existing user)
- [ ] Store social provider ID and provider name for future logins
- [ ] Support unlinking a social account
- [ ] Support multiple social accounts per user
- [ ] Write tests for creation, linking, and conflict scenarios

## Social Auth: Callback Flow

- [ ] Register callback route (`/auth/{provider}/callback`)
- [ ] Validate state parameter on callback
- [ ] Handle authorization denied by user
- [ ] Handle provider errors (invalid code, expired code)
- [ ] Authenticate user after successful callback
- [ ] Redirect to configurable post-login URL
- [ ] Fire events (`SocialLogin`, `SocialAccountLinked`, `SocialAccountCreated`)
- [ ] Write integration tests for the full redirect -> callback -> auth flow

## Testing Utilities

- [ ] Create `FakeSocialProvider` for testing without real HTTP calls
- [ ] Create `FakeOAuthClient` for testing OAuth flows in feature tests
- [ ] Document testing patterns and fakes in package README

## Documentation

- [ ] Document OAuth2 authorization code + PKCE flow with sequence diagrams
- [ ] Document social auth setup (GitHub, Google) with step-by-step config
- [ ] Document token management (introspection, revocation, rotation)
- [ ] Document testing patterns (using fakes, mocking providers)
- [ ] Add examples to docs/guides/
