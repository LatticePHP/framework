# ADR-006: Package Map

**Date:** 2026-03-21
**Status:** Accepted

## Context

A modular framework requires careful decomposition into packages. Too few packages create a monolith; too many create dependency management overhead. Each package must have a clear responsibility, minimal coupling to other packages, and a well-defined public API.

LatticePHP is developed as a monorepo for practical development reasons (shared CI, atomic cross-package changes, easier refactoring) but is distributed as independent Composer packages so that users install only what they need.

## Decision

The framework is organized into approximately 35 packages across six categories:

### Core (foundation packages -- most other packages depend on these)

| Package | Namespace | Responsibility |
|---------|-----------|----------------|
| `lattice/contracts` | `Lattice\Contracts\` | Interfaces and abstract contracts for all framework components |
| `lattice/core` | `Lattice\Core\` | DI container, application lifecycle, configuration, environment |
| `lattice/compiler` | `Lattice\Compiler\` | Attribute scanning, compilation passes, container optimization |
| `lattice/module` | `Lattice\Module\` | Module loader, dependency resolution, isolation boundaries |
| `lattice/pipeline` | `Lattice\Pipeline\` | Pipeline/middleware engine (Pipes, Interceptors, Guards, Filters) |
| `lattice/testing` | `Lattice\Testing\` | Test helpers, module testing utilities, in-memory fakes |

### HTTP / API (request handling and API tooling)

| Package | Namespace | Responsibility |
|---------|-----------|----------------|
| `lattice/http` | `Lattice\Http\` | PSR-7/PSR-15 HTTP layer, request/response, middleware |
| `lattice/routing` | `Lattice\Routing\` | Attribute-based routing, route compilation, URL generation |
| `lattice/validation` | `Lattice\Validation\` | Input validation with attribute and programmatic APIs |
| `lattice/openapi` | `Lattice\OpenApi\` | OpenAPI spec generation from code, schema inference |
| `lattice/problem-details` | `Lattice\ProblemDetails\` | RFC 9457 Problem Details error responses |
| `lattice/jsonapi` | `Lattice\JsonApi\` | JSON:API specification support |
| `lattice/rate-limit` | `Lattice\RateLimit\` | Rate limiting with multiple backend strategies |

### Data (persistence, caching, events, scheduling)

| Package | Namespace | Responsibility |
|---------|-----------|----------------|
| `lattice/database` | `Lattice\Database\` | Database abstraction, query builder, migrations, connection management |
| `lattice/cache` | `Lattice\Cache\` | PSR-6/PSR-16 cache with tagged invalidation |
| `lattice/filesystem` | `Lattice\Filesystem\` | File storage abstraction (local, S3, GCS) |
| `lattice/events` | `Lattice\Events\` | Event dispatcher, listeners, subscribers |
| `lattice/scheduler` | `Lattice\Scheduler\` | Cron-like task scheduling |

### Auth (authentication and authorization)

| Package | Namespace | Responsibility |
|---------|-----------|----------------|
| `lattice/auth` | `Lattice\Auth\` | Core authentication framework, guard system |
| `lattice/jwt` | `Lattice\Jwt\` | JWT token generation, validation, refresh (asymmetric default) |
| `lattice/pat` | `Lattice\Pat\` | Personal Access Tokens |
| `lattice/api-key` | `Lattice\ApiKey\` | API key authentication |
| `lattice/oauth` | `Lattice\OAuth\` | OAuth2 server / OIDC provider |
| `lattice/social` | `Lattice\Social\` | Social login (GitHub, Google, etc.) |
| `lattice/authorization` | `Lattice\Authorization\` | RBAC, policies, permissions, gates |

### Async (queues, messaging, workflows, distributed systems)

| Package | Namespace | Responsibility |
|---------|-----------|----------------|
| `lattice/queue` | `Lattice\Queue\` | Job dispatching and worker framework |
| `lattice/microservices` | `Lattice\Microservices\` | Service discovery, health checks, circuit breakers |
| `lattice/grpc` | `Lattice\Grpc\` | gRPC server and client support |
| `lattice/transport-nats` | `Lattice\Transport\Nats\` | NATS messaging transport |
| `lattice/transport-rabbitmq` | `Lattice\Transport\RabbitMQ\` | RabbitMQ messaging transport |
| `lattice/transport-sqs` | `Lattice\Transport\Sqs\` | AWS SQS transport |
| `lattice/transport-kafka` | `Lattice\Transport\Kafka\` | Kafka transport |
| `lattice/workflow` | `Lattice\Workflow\` | Durable workflow engine (orchestration, replay, signals, queries) |
| `lattice/workflow-store` | `Lattice\Workflow\Store\` | Workflow event store, persistence backends |

### Runtime (server integration, observability, developer tools)

| Package | Namespace | Responsibility |
|---------|-----------|----------------|
| `lattice/roadrunner` | `Lattice\RoadRunner\` | RoadRunner application server integration |
| `lattice/openswoole` | `Lattice\OpenSwoole\` | OpenSwoole runtime integration (experimental) |
| `lattice/observability` | `Lattice\Observability\` | OpenTelemetry traces, metrics, structured logging |
| `lattice/devtools` | `Lattice\DevTools\` | Code generators, REPL, debug toolbar |

### Starters (project templates)

| Package | Description |
|---------|-------------|
| `lattice/starter-api` | REST API project template |
| `lattice/starter-service` | Microservice project template |
| `lattice/starter-workflow` | Workflow-centric project template |
| `lattice/starter-grpc` | gRPC service project template |

## Consequences

**Positive:**
- Each package has a single, clear responsibility.
- Users install only what they need. An API project does not pull in workflow or gRPC dependencies.
- Independent packages can be versioned and released separately when needed.
- The monorepo structure keeps development efficient with cross-cutting changes.
- Starter packages provide opinionated starting points without locking users in.

**Negative:**
- ~35 packages is a significant maintenance surface.
- Cross-package dependency management requires tooling (monorepo split, version constraints).
- New contributors must understand the package boundaries to contribute effectively.
- Some packages may have very thin implementations initially (transport packages, OpenSwoole).

## Alternatives Considered

1. **Fewer, larger packages (10-15):** Would reduce maintenance overhead but create unwanted coupling. A user wanting just HTTP + routing should not be forced to pull in queue or workflow code.

2. **More granular packages (50+):** Diminishing returns. Splitting `lattice/http` into `lattice/request`, `lattice/response`, `lattice/middleware` would create excessive wiring without meaningful isolation benefits.

3. **No monorepo (individual repositories):** Makes cross-package development extremely painful. Atomic changes across packages become multi-PR affairs. Monorepo with subtree split is the industry standard for framework development.
