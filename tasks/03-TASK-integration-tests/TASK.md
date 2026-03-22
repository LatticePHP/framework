# 03 — Integration Tests

> End-to-end tests against real services (Postgres, Redis, RabbitMQ, NATS, Kafka, LocalStack)

## Dependencies
- None (Wave 1)
- Packages: most of `packages/` — tests exercise real connections across the framework

## Subtasks

### 1. [ ] CI infrastructure setup
- Create Docker Compose file with all required services: Redis, PostgreSQL, NATS, RabbitMQ, Redpanda/Kafka, LocalStack (SQS)
- Add GitHub Actions workflow for integration test suite
- Configure separate `phpunit-integration.xml` for integration test suite
- Add health-check waits so tests only run after services are ready
- Configure code coverage reporting and threshold enforcement (80% target)
- **Verify:** `docker compose up -d` starts all services, health checks pass, `phpunit -c phpunit-integration.xml` runs (even if no tests yet)

### 2. [ ] Transport tests — gRPC
- Stand up a real gRPC server in test using OpenSwoole/RoadRunner
- Test unary RPC call end-to-end
- Test server streaming, client streaming, and bidirectional streaming RPC
- Test gRPC error/status code propagation
- Test gRPC metadata/header passing
- Test TLS configuration
- **Verify:** All gRPC integration tests pass against a real server process

### 3. [ ] Transport tests — NATS
- Connect to real NATS server in Docker
- Test publish/subscribe round-trip
- Test request/reply pattern
- Test queue group load balancing
- Test connection recovery after NATS restart
- Test JetStream persistent messaging
- **Verify:** All NATS tests pass against Docker NATS instance

### 4. [ ] Transport tests — RabbitMQ
- Connect to real RabbitMQ server in Docker
- Test publish/consume round-trip
- Test exchange and queue declaration
- Test message acknowledgment and rejection
- Test dead-letter queue routing
- Test connection recovery after broker restart
- **Verify:** All RabbitMQ tests pass against Docker RabbitMQ instance

### 5. [ ] Transport tests — SQS (LocalStack)
- Connect to LocalStack SQS in Docker
- Test send/receive round-trip
- Test message visibility timeout
- Test dead-letter queue configuration
- Test batch send/receive
- Test FIFO queue ordering guarantees
- **Verify:** All SQS tests pass against LocalStack

### 6. [ ] Transport tests — Kafka (Redpanda)
- Connect to Redpanda (Kafka-compatible) in Docker
- Test produce/consume round-trip
- Test consumer group rebalancing
- Test offset commit and replay
- Test partition assignment
- Test message key-based routing
- **Verify:** All Kafka tests pass against Redpanda instance

### 7. [ ] OAuth2 full grant flow test
- Test full authorization code grant flow (authorize -> callback -> token)
- Test authorization code + PKCE flow end-to-end
- Test client credentials grant flow
- Test refresh token grant flow
- Test token introspection and revocation endpoints
- Test invalid/expired token rejection
- Test scope enforcement across the full flow
- **Verify:** All OAuth2 grant flows complete successfully end-to-end

### 8. [ ] Social auth test with mock provider
- Set up mock OAuth provider HTTP server
- Test redirect-to-provider flow
- Test callback handling with valid authorization code
- Test state parameter validation (CSRF protection)
- Test user creation from provider profile and linking to existing account
- Test error handling for denied authorization
- **Verify:** Full social login flow works against mock provider

### 9. [ ] Rate limiting with Redis backend
- Test fixed-window limiter with Redis backend
- Test sliding-window limiter with Redis backend
- Test token-bucket limiter with Redis backend
- Test rate limit headers in HTTP response
- Test concurrent request handling (race conditions)
- Test rate limit reset after window expiry
- **Verify:** All rate limiting strategies work correctly with real Redis

### 10. [ ] CORS config integration test
- Test preflight OPTIONS request handling
- Test allowed origins, methods, and headers configuration
- Test credentials support
- Test wildcard vs. explicit origin behavior
- **Verify:** CORS headers are set correctly for all configuration combinations

### 11. [ ] Database multi-tenancy test
- Test database-per-tenant mode with real PostgreSQL
- Test tenant switching mid-request
- Test tenant isolation (tenant A cannot see tenant B data)
- Test tenant migration runner
- Test schema-based multi-tenancy
- **Verify:** Full multi-tenant CRUD operations work with real PostgreSQL

### 12. [ ] Route caching + OpenAPI generation CLI tests
- Test `route:cache` CLI command generates valid cache file
- Test cached routes match non-cached routes exactly
- Test `route:clear` removes cache file
- Test application boots correctly from cached routes
- Test OpenAPI spec generation from annotated routes
- Test generated spec validates against OpenAPI 3.1 schema
- Test request body, response, and parameter schema generation
- **Verify:** Cache round-trip produces identical routing; generated spec passes OpenAPI validation

### 13. [ ] Workflow integration tests
- Test workflow execution with queue-dispatched activities (not sync)
- Test workflow with multiple sequential and parallel activities
- Test activity timeout and retry behavior
- Test workflow failure and compensation
- Test workflow pause/resume via signals
- Test workflow event store persistence and replay
- Test long-running workflow with timer
- **Verify:** Full workflow lifecycle works end-to-end with real queue and event store

### 14. [ ] Laravel Bridge tests
- Test LatticePHP module loads inside a Laravel application
- Test service provider registration
- Test configuration publishing
- Test route registration within Laravel router
- **Verify:** Lattice modules function correctly within a Laravel host app

### 15. [ ] Coverage and reporting
- Generate combined coverage report across all integration tests
- Verify coverage meets 80% threshold
- Add coverage badge to root README
- Document how to run integration tests locally with Docker
- **Verify:** Coverage report generates, threshold enforced in CI

## Integration Verification
- [ ] Full CI pipeline runs: Docker services start, all integration tests pass, coverage reported
- [ ] Tests are isolated — each test class can run independently
- [ ] All tests pass on both Linux (CI) and macOS/Windows (local with Docker)
- [ ] Test execution time is under 10 minutes total
