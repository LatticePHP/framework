# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `CrudService` base class for transaction-wrapped CRUD with lifecycle hooks
- `CrudController` base class with generic index/show/destroy endpoints
- `TestCase::bootTestDatabase()` helper for in-memory SQLite test setup
- `TestCase::generateTestToken()` helper for JWT test authentication
- `QueryFilter::setMaxPerPage()` to prevent pagination DoS (default: 100)
- Model enum constants pattern (Contact::STATUSES, Deal::STAGES, etc.)
- 73 new CRM E2E tests (Activities, Companies, Notes, extended Workspaces)

### Changed
- `Auditable` trait uses internal backing store — models no longer need to declare static properties
- `RefreshDatabase` auto-clears all booted Eloquent models and resets WorkspaceContext
- CRM controllers/services refactored to use CrudService/CrudController (-335 lines)

### Fixed
- JwtAuthenticationGuard returns 401 (not 403) for missing/invalid tokens
- DtoMapper collects ALL missing required fields before throwing
- QueryFilter caps per_page at 100 to prevent unbounded queries

## [1.0.0] - 2026-03-22

### Added
- Initial framework release with 42 packages
- Module system with `#[Module]` attribute and dependency graph
- Compiler with attribute discovery and manifest generation
- HTTP kernel with attribute-based routing (`#[Controller]`, `#[Get]`, `#[Post]`, etc.)
- Pipeline: guards, pipes, interceptors, exception filters
- Validation with DTO mapping and attribute-based rules
- JWT authentication with refresh token rotation
- Personal Access Tokens (PAT) module
- API Key authentication module
- OAuth2/OIDC server with 3 grant types (client_credentials, authorization_code, refresh_token)
- Social auth (headless) module
- Authorization with policies, gates, roles, scopes, tenant-aware checks
- Native durable workflow engine with deterministic replay, signals, queries, compensation
- Database-backed workflow event store for production persistence
- Database layer with QueryBuilder, SchemaBuilder, migrations, seeders, pagination
- Queue system with sync, in-memory, and database drivers
- Event dispatcher with priority and async support
- Cache with array, file, and Redis drivers
- Filesystem with local and in-memory drivers
- Scheduler with cron expression support
- Microservices with message routing and transport abstraction
- gRPC routing and execution context
- Transport adapters: NATS, RabbitMQ, SQS, Kafka (with fakes for testing)
- RoadRunner integration (worker lifecycle, memory guard, graceful shutdown)
- Observability: structured logging, metrics, tracing, health checks, audit events
- Problem Details (RFC 9457) error responses
- OpenAPI specification generation from route metadata
- JSON:API serialization/deserialization
- Rate limiting with guard integration
- HTTP client for outbound requests
- Mail system with SMTP and log transports
- Notification system with mail and database channels
- Console command framework with built-in commands
- Password hashing (bcrypt, argon2id)
- Encryption (AES-256-GCM)
- CORS support
- URL generation
- String and array helpers
- Serializer package (JSON, PHP native)
- Testing harness with module overrides, HTTP client, fakes
- Devtools with 8 code generators
- 4 starter applications (API, Workflow, Service, gRPC)
- 14 Architecture Decision Records
- 17 documentation guides

[Unreleased]: https://github.com/LatticePHP/framework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/LatticePHP/framework/releases/tag/v1.0.0
