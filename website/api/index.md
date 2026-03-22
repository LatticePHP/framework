---
outline: deep
---

# API Reference

## Core Packages

### lattice/core

The application kernel, bootstrap, and lifecycle management.

| Class | Purpose |
|---|---|
| `Lattice\Core\Application` | Application kernel -- boots modules, handles requests |
| `Lattice\Core\Config` | Configuration repository with dot-notation access |
| `Lattice\Core\Environment` | Environment detection and `.env` loading |

### lattice/module

Module system with NestJS-style DI and provider model.

| Class / Attribute | Purpose |
|---|---|
| `#[Module]` | Declares a module with imports, exports, providers, controllers |
| `ModuleDefinition` | Compiled module metadata |
| `DynamicModuleDefinition` | Runtime-configured modules with factory bindings |

### lattice/compiler

Attribute discovery, module graph resolution, and manifest compilation.

| Class | Purpose |
|---|---|
| `AttributeScanner` | Discovers PHP attributes across the codebase |
| `ModuleGraph` | Resolves module dependency graph |
| `CompiledManifest` | Production-cached metadata (zero reflection at runtime) |

## HTTP Layer

### lattice/http

| Class | Purpose |
|---|---|
| `HttpKernel` | Wires route matching, guard resolution, pipeline, controller |
| `Request` | Extended Symfony Request with JSON helpers |
| `Response` | HTTP response with status, headers, body |
| `ResponseFactory` | Static factory: `json()`, `created()`, `noContent()`, `paginated()` |
| `Resource` | API resource for model serialization |

### lattice/routing

| Attribute | Purpose |
|---|---|
| `#[Controller('/path')]` | Declares a controller with base path |
| `#[Get('/')]`, `#[Post('/')]`, `#[Put('/')]`, `#[Delete('/')]`, `#[Patch('/')]` | HTTP method + path |
| `#[Body]` | Deserialize request body into DTO |
| `#[Query]` | Bind query string parameters |
| `#[Param]` | Bind route parameters |
| `#[Header]` | Bind request headers |
| `#[CurrentUser]` | Inject authenticated principal |

### lattice/validation

| Attribute | Purpose |
|---|---|
| `#[Required]` | Field is required |
| `#[Email]` | Must be valid email |
| `#[StringType(minLength, maxLength)]` | String length constraints |
| `#[Numeric(min, max)]` | Numeric range |
| `#[InArray(values)]` | Must be one of the listed values |
| `#[Unique(table, column)]` | Database uniqueness check |
| `#[Nullable]` | Field may be null |

## Pipeline

### lattice/pipeline

| Interface / Attribute | Purpose |
|---|---|
| `Guard` | `canActivate(ExecutionContext): bool` -- gate access |
| `Interceptor` | `intercept(ExecutionContext, CallHandler): mixed` -- before/after |
| `Pipe` | `transform(mixed $value, ArgumentMetadata): mixed` -- input transform |
| `ExceptionFilter` | `catch(Throwable, ExecutionContext): Response` -- error handling |
| `#[UseGuards(guards)]` | Apply guards to controller/method |
| `#[UseInterceptors(interceptors)]` | Apply interceptors |
| `#[UsePipes(pipes)]` | Apply pipes |
| `#[UseFilters(filters)]` | Apply exception filters |
| `PipelineExecutor` | Runs the full guard/interceptor/pipe pipeline |

## Authentication

### lattice/auth

| Class | Purpose |
|---|---|
| `JwtAuthenticationGuard` | JWT Bearer token guard |
| `JwtEncoder` | Encode/decode JWT tokens |
| `JwtConfig` | JWT configuration (secret, algorithm, TTLs) |
| `HashManager` | Password hashing (bcrypt, argon2id) |
| `Principal` / `PrincipalInterface` | Authenticated identity abstraction |

### lattice/jwt

| Class | Purpose |
|---|---|
| `TokenIssuer` | Issues access + refresh token pairs |
| `RefreshTokenRotator` | Rotate refresh tokens with revocation |

### lattice/authorization

| Attribute | Purpose |
|---|---|
| `#[Authorize]` | Requires any authenticated user |
| `#[Roles(roles)]` | Requires specific roles |
| `#[Scopes(scopes)]` | Requires token scopes |
| `#[Policy(model)]` | Registers a policy class |
| `#[Can(ability, model)]` | Checks policy on controller method |

## Database

### lattice/database

| Class | Purpose |
|---|---|
| `Model` | Base Eloquent model (extends `Illuminate\Database\Eloquent\Model`) |
| `Factory` | Base model factory |
| `BelongsToWorkspace` | Trait: auto-scopes queries to current workspace |
| `BelongsToTenant` | Trait: auto-scopes queries to current tenant |

## Workflow Engine

### lattice/workflow

| Class / Attribute | Purpose |
|---|---|
| `#[Workflow(name, taskQueue)]` | Declares a workflow class |
| `#[Activity(name)]` | Declares an activity class |
| `#[SignalMethod(name)]` | Declares a signal handler |
| `#[QueryMethod(name)]` | Declares a query handler |
| `WorkflowContext` | Primary API inside workflows |
| `WorkflowClient` | Start, signal, query, cancel workflows |
| `WorkflowHandle` | Handle to a running workflow |
| `CompensationScope` | Saga/compensation pattern |
| `WorkflowOptions` | Configuration for starting workflows |

### lattice/workflow-store

| Class | Purpose |
|---|---|
| `InMemoryEventStore` | Testing event store |
| `DatabaseEventStore` | Production event store (SQLite/MySQL/PostgreSQL) |

## Microservices

### lattice/microservices

| Attribute / Class | Purpose |
|---|---|
| `#[MessageController(transport)]` | Message-based controller |
| `#[CommandPattern(pattern)]` | Request-response message handler |
| `#[EventPattern(pattern)]` | Fire-and-forget handler |
| `#[ReplyPattern(pattern)]` | Query/reply handler |
| `MessageEnvelope` | Message wrapper with ID, type, payload, correlation |
| `MessageRouter` | Routes envelopes to handlers |
| `TransportInterface` | publish, subscribe, acknowledge, reject |
| `InMemoryTransport` | Testing transport |

### Transport Packages

| Package | Transport |
|---|---|
| `lattice/transport-nats` | NATS |
| `lattice/transport-rabbitmq` | RabbitMQ |
| `lattice/transport-sqs` | Amazon SQS |
| `lattice/transport-kafka` | Apache Kafka |

## Observability

### lattice/observability

| Class | Purpose |
|---|---|
| `Log` | Static logging facade (PSR-3) |
| `StructuredLogger` | Logger with correlation ID and structured context |
| `CorrelationContext` | Trace context propagation |
| `MetricsCollector` | Counters, gauges, histograms |
| `OtelExporter` | OpenTelemetry OTLP export |
| `HealthController` | `/health`, `/health/live`, `/health/ready` |
| `AuditLogger` | Structured audit events |
| `AuditLog` | Eloquent model for audit queries |

## Testing

### lattice/testing

| Class / Trait | Purpose |
|---|---|
| `TestCase` | Base test class with HTTP helpers |
| `TestResponse` | Fluent assertion API |
| `RefreshDatabase` | Migrate + truncate per test |
| `DatabaseTransactions` | Rollback per test |
| `WithAuthentication` | Auto-authenticate |
| `WithWorkspace` | Auto-set workspace context |
| `FakeEventBus` | Capture dispatched events |
| `FakeQueueDispatcher` | Capture dispatched jobs |
| `FakeAuthGuard` | Always-allow auth guard |
| `FakePrincipal` | Configurable test identity |

## Enterprise

### lattice/rate-limit

| Attribute | Purpose |
|---|---|
| `#[RateLimit(maxAttempts, decaySeconds, key)]` | Rate limiting |

### lattice/cache

Cache integration using `illuminate/cache`.

### lattice/events

Event dispatcher using `illuminate/events` with `#[Listener]` attribute discovery.

### lattice/queue

Job queue using `illuminate/queue` with `QueueDispatcher`.

### lattice/scheduler

Task scheduling with `#[Schedule]` attribute and cron expressions.

### lattice/problem-details

RFC 9457 error responses with structured problem details.

## Runtime

### lattice/roadrunner

| Class | Purpose |
|---|---|
| `RoadRunnerHttpWorker` | Long-running HTTP worker |
| `RoadRunnerConfig` | Worker configuration |
| `WorkerLifecycle` | Startup/request/drain/shutdown hooks |
| `ContainerResetter` | Clear state between requests |
| `MemoryGuard` | Memory limit enforcement |
| `GracefulShutdown` | Signal handling |
