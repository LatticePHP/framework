# OAuth & Social Auth — Complete the Auth Stack

## Overview

Complete the `lattice/oauth` (OAuth2 server) and `lattice/social` (social authentication) packages. The OAuth2 server needs the remaining grant flows, token management endpoints, and client management tooling. The social auth package needs concrete provider implementations (GitHub, Google) and the full redirect/callback/user-linking flow.

## Scope

### OAuth2 Server (`lattice/oauth`)
Full RFC 6749 / RFC 7636 compliance with authorization code + PKCE, client credentials, and refresh token grants. Token introspection (RFC 7662), token revocation (RFC 7009), scope management, JWT access tokens, and CLI commands for client management.

### Social Auth (`lattice/social`)
Provider abstraction with concrete GitHub and Google implementations. Complete redirect/callback flow with state parameter CSRF protection, user creation or linking to existing accounts, and test fakes for CI.

## Success Criteria

1. Authorization code + PKCE flow works end-to-end.
2. Token introspection and revocation endpoints function correctly.
3. Refresh token rotation prevents token reuse.
4. GitHub and Google social logins work end-to-end.
5. Social auth correctly creates new users or links to existing accounts.
6. All flows have integration tests.

## Dependencies

| Package | Role |
|---|---|
| `lattice/auth` | Authentication guard integration |
| `lattice/jwt` | JWT access token encoding/decoding |
| `lattice/http` | Request/response handling for endpoints |
| `lattice/database` | Token, client, and authorization code persistence |
| `lattice/http-client` | Outbound HTTP for social provider APIs |
| `lattice/routing` | Route registration for OAuth endpoints |
