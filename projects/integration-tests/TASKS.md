# Integration Tests — Task List

## CI Infrastructure

- [ ] Create Docker Compose file with all required services (Redis, PostgreSQL, NATS, RabbitMQ, Redpanda/Kafka, LocalStack)
- [ ] Add GitHub Actions workflow for integration test suite
- [ ] Configure PHPUnit integration test suite (separate phpunit-integration.xml)
- [ ] Add health-check waits so tests only run after services are ready
- [ ] Configure code coverage reporting and threshold enforcement

## gRPC (`lattice/grpc`)

- [ ] Stand up a real gRPC server in test using OpenSwoole/RoadRunner
- [ ] Test unary RPC call end-to-end
- [ ] Test server streaming RPC
- [ ] Test client streaming RPC
- [ ] Test bidirectional streaming RPC
- [ ] Test gRPC error/status code propagation
- [ ] Test gRPC metadata/header passing
- [ ] Test TLS configuration

## Transports — NATS (`lattice/transport-nats`)

- [ ] Connect to real NATS server in Docker
- [ ] Test publish/subscribe round-trip
- [ ] Test request/reply pattern
- [ ] Test queue group load balancing
- [ ] Test connection recovery after NATS restart
- [ ] Test JetStream persistent messaging

## Transports — RabbitMQ (`lattice/transport-rabbitmq`)

- [ ] Connect to real RabbitMQ server in Docker
- [ ] Test publish/consume round-trip
- [ ] Test exchange and queue declaration
- [ ] Test message acknowledgment and rejection
- [ ] Test dead-letter queue routing
- [ ] Test connection recovery after broker restart

## Transports — SQS (`lattice/transport-sqs`)

- [ ] Connect to LocalStack SQS in Docker
- [ ] Test send/receive round-trip
- [ ] Test message visibility timeout
- [ ] Test dead-letter queue configuration
- [ ] Test batch send/receive
- [ ] Test FIFO queue ordering guarantees

## Transports — Kafka (`lattice/transport-kafka`)

- [ ] Connect to Redpanda (Kafka-compatible) in Docker
- [ ] Test produce/consume round-trip
- [ ] Test consumer group rebalancing
- [ ] Test offset commit and replay
- [ ] Test partition assignment
- [ ] Test message key-based routing

## OAuth2 Server (`lattice/oauth`)

- [ ] Test full authorization code grant flow (authorize -> callback -> token)
- [ ] Test authorization code + PKCE flow end-to-end
- [ ] Test client credentials grant flow
- [ ] Test refresh token grant flow
- [ ] Test token introspection endpoint
- [ ] Test token revocation endpoint
- [ ] Test invalid/expired token rejection
- [ ] Test scope enforcement across the full flow

## Social Auth (`lattice/social`)

- [ ] Set up mock OAuth provider HTTP server
- [ ] Test redirect-to-provider flow
- [ ] Test callback handling with valid authorization code
- [ ] Test state parameter validation (CSRF protection)
- [ ] Test user creation from provider profile
- [ ] Test user linking to existing account
- [ ] Test error handling for denied authorization

## Workflow (`lattice/workflow`, `lattice/workflow-store`)

- [ ] Test workflow execution with queue-dispatched activities (not sync)
- [ ] Test workflow with multiple sequential activities
- [ ] Test workflow with parallel activity execution
- [ ] Test activity timeout and retry behavior
- [ ] Test workflow failure and compensation
- [ ] Test workflow pause/resume via signals
- [ ] Test workflow event store persistence and replay
- [ ] Test long-running workflow with timer

## Rate Limiting (`lattice/rate-limit`)

- [ ] Test fixed-window limiter with Redis backend
- [ ] Test sliding-window limiter with Redis backend
- [ ] Test token-bucket limiter with Redis backend
- [ ] Test rate limit headers in HTTP response
- [ ] Test concurrent request handling (race conditions)
- [ ] Test rate limit reset after window expiry

## CORS (`lattice/http`)

- [ ] Test preflight OPTIONS request handling
- [ ] Test allowed origins configuration
- [ ] Test allowed methods configuration
- [ ] Test allowed headers configuration
- [ ] Test credentials support
- [ ] Test wildcard vs. explicit origin behavior

## Database Multi-Tenancy (`lattice/database`)

- [ ] Test database-per-tenant mode with real PostgreSQL
- [ ] Test tenant switching mid-request
- [ ] Test tenant isolation (tenant A cannot see tenant B data)
- [ ] Test tenant migration runner
- [ ] Test schema-based multi-tenancy

## Route Caching (`lattice/routing`)

- [ ] Test `route:cache` CLI command generates valid cache file
- [ ] Test cached routes match non-cached routes exactly
- [ ] Test `route:clear` removes cache file
- [ ] Test application boots correctly from cached routes
- [ ] Test cache invalidation on route changes

## OpenAPI Generation (`lattice/openapi`)

- [ ] Test spec generation from annotated routes
- [ ] Test generated spec validates against OpenAPI 3.1 schema
- [ ] Test request body schema generation
- [ ] Test response schema generation
- [ ] Test parameter extraction (path, query, header)

## Laravel Bridge (`lattice/devtools`)

- [ ] Test LatticePHP module loads inside a Laravel application
- [ ] Test service provider registration
- [ ] Test configuration publishing
- [ ] Test route registration within Laravel router

## Coverage & Reporting

- [ ] Generate combined coverage report across all integration tests
- [ ] Verify coverage meets 80% threshold
- [ ] Add coverage badge to root README
- [ ] Document how to run integration tests locally with Docker
