# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Initial framework release with 40+ packages
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
- 16 documentation guides
