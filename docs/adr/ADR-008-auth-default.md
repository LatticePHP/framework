# ADR-008: Authentication Default

**Date:** 2026-03-21
**Status:** Accepted

## Context

An API-first framework needs stateless authentication by default. Session-based authentication, while appropriate for traditional web applications, introduces server-side state that conflicts with horizontal scaling, microservice communication, and the stateless nature of REST APIs.

LatticePHP must ship with a secure, production-ready authentication system out of the box while supporting multiple authentication strategies for different use cases. The default should require zero additional infrastructure (no Redis sessions, no OAuth server) and work immediately after installation.

## Decision

### Default Authentication: JWT with Asymmetric Keys

The default authentication strategy is **JWT access + refresh tokens using asymmetric cryptography (RS256/ES256)**:

- **Access token:** Short-lived (15 minutes default), contains user claims, used for API authorization.
- **Refresh token:** Longer-lived (7 days default), stored hashed in database, used to obtain new access tokens.
- **Key pair:** Ed25519 or RSA-2048 generated during project setup. Private key signs tokens; public key verifies them.
- **Token rotation:** Refresh tokens are single-use. Each refresh issues a new refresh token and invalidates the old one.
- **Revocation:** Access tokens expire naturally. Refresh tokens can be explicitly revoked. Optional token blacklist for immediate access token invalidation.

### Optional Authentication Modules

| Package | Strategy | Use Case |
|---------|----------|----------|
| `lattice/jwt` | JWT (default) | API authentication, service-to-service |
| `lattice/pat` | Personal Access Tokens | Developer APIs, CI/CD integrations |
| `lattice/api-key` | API Keys | Third-party integrations, simple machine auth |
| `lattice/social` | Social Login | GitHub, Google, etc. via OAuth2 providers |
| `lattice/oauth` | OAuth2 Server / OIDC | When LatticePHP IS the identity provider |

### Guard System

Authentication strategies are implemented as Guards that can be composed:

```
#[UseGuards(JwtGuard::class)]       // Single guard
#[UseGuards(JwtGuard::class, ApiKeyGuard::class)]  // Either/or
```

Multiple guards are evaluated in order. The first one that succeeds authenticates the request. If all fail, a 401 response is returned.

### Configuration Defaults

- Algorithm: ES256 (ECDSA with P-256) preferred, RS256 supported.
- Access token TTL: 15 minutes.
- Refresh token TTL: 7 days.
- Refresh token rotation: enabled.
- Token storage: `Authorization: Bearer <token>` header only. No cookie-based token transport.

## Consequences

**Positive:**
- Zero additional infrastructure for default auth. Database + application is sufficient.
- Asymmetric keys allow services to verify tokens without access to the signing key (important for microservices).
- Stateless verification means no per-request database lookups for access tokens.
- Refresh token rotation prevents token reuse attacks.
- Each auth strategy is a separate package, so unused strategies add zero overhead.

**Negative:**
- JWT has inherent limitations: tokens cannot be instantly revoked without a blacklist.
- Asymmetric key management adds operational complexity (key rotation, distribution).
- Developers accustomed to session-based auth must adapt to token-based patterns.
- Multiple auth packages to maintain.

## Alternatives Considered

1. **Session-based authentication default:** Requires server-side storage (Redis/database), breaks statelessness, and does not align with API-first positioning.

2. **Opaque tokens (non-JWT) default:** Requires database lookup on every request. Simpler but less performant and does not support distributed verification.

3. **Symmetric JWT (HS256) default:** Simpler key management but every service that verifies tokens needs the shared secret, which is a security risk in microservice architectures.

4. **Passport/Sanctum-style hybrid:** Mixes token types in ways that can confuse developers. Clear separation between JWT, PAT, and API keys is more explicit.
