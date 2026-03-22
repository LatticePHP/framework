---
outline: deep
---

# Why LatticePHP

## What It Is

LatticePHP is a backend-only PHP framework for building API services, microservices, and durable workflows. It uses Laravel's Illuminate components as its engine while providing a NestJS-style modular architecture on top. It runs on PHP 8.4+.

---

## The Architecture

LatticePHP combines three layers:

1. **Illuminate components underneath.** Eloquent ORM, Query Builder, Cache, Queue, Events, Validation -- all the battle-tested Laravel internals, used directly without the Laravel framework wrapper.

2. **NestJS-style module system on top.** Every feature lives in a `#[Module]` with explicit imports, providers, controllers, and exports. Dependencies are visible in the code, not scattered across service providers.

3. **Unique features in the middle.** A native durable workflow engine, attribute-based compilation, guard/pipe/interceptor pipeline, multi-workspace tenancy, and transport-aware controllers.

---

## Who It Is For

- **Teams building API backends** that outgrow Laravel's conventions but do not want to abandon its ecosystem
- **PHP developers who want NestJS-style architecture** (modules, DI, decorators/attributes) without leaving PHP
- **Projects that need durable workflows** (order processing, onboarding, payment orchestration) without running a separate Temporal cluster
- **Microservice teams** that want typed message controllers with pluggable transports (NATS, RabbitMQ, SQS, Kafka)
- **Multi-tenant SaaS products** that need built-in workspace isolation and tenant scoping

---

## What Makes It Different

### Module System with Explicit Dependencies

Every feature is a self-contained module. No implicit global state, no service provider boot ordering issues.

```php
#[Module(
    imports: [DatabaseModule::class, CacheModule::class],
    providers: [OrderService::class, OrderRepository::class],
    controllers: [OrderController::class],
    exports: [OrderService::class],
)]
final class OrdersModule {}
```

If `OrdersModule` needs `PaymentGateway`, it must import `PaymentsModule`, which must export it. The dependency graph is explicit and enforced.

### Attribute-Based Everything

Routes, guards, validation, authorization, rate limiting, scheduling, and audit logging are all declared via PHP attributes:

```php
#[Controller('/api/orders')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
#[RateLimit(maxAttempts: 60, decaySeconds: 60)]
final class OrderController
{
    #[Post('/')]
    #[Roles(roles: ['admin', 'manager'])]
    #[AuditAction('Created order', category: 'billing')]
    public function create(#[Body] CreateOrderDto $dto): Response { ... }
}
```

In production, `php lattice compile` scans all attributes and produces a cached manifest. Zero runtime reflection.

### Guard / Pipe / Interceptor Pipeline

LatticePHP separates cross-cutting concerns by type instead of lumping everything into middleware:

| Type | Purpose | Example |
|---|---|---|
| **Guard** | Authentication and authorization (yes/no gate) | `JwtAuthenticationGuard`, `AdminGuard`, `WorkspaceGuard` |
| **Pipe** | Input transformation and validation | `ValidationPipe`, `TrimStringsPipe` |
| **Interceptor** | Before/after logic, logging, caching | `RequestLoggingInterceptor`, `TracingInterceptor` |
| **Filter** | Exception handling and error formatting | `ProblemDetailsFilter` |

### Native Durable Workflow Engine

Write long-running business processes as plain PHP. The engine provides deterministic replay, event sourcing, compensation/saga, signals, and queries -- without an external Temporal service.

```php
#[Workflow]
final class OnboardingWorkflow
{
    public function execute(WorkflowContext $ctx, int $userId): void
    {
        $ctx->executeActivity(AccountService::class, 'create', $userId);
        $ctx->executeActivity(EmailService::class, 'sendWelcome', $userId);
        $ctx->sleep(86400); // Wait 24 hours (durable timer)
        $ctx->executeActivity(EmailService::class, 'sendFollowUp', $userId);
    }
}
```

Every activity result is recorded. If the process crashes after `sendWelcome`, replay skips the completed activities and resumes at `sleep`. The engine uses your existing database and queue -- no additional infrastructure.

### Built-In Multi-Tenancy

Workspace and tenant isolation are first-class concepts, not afterthought packages:

```php
#[Controller('/api/projects')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ProjectController { ... }

// Model
final class Project extends Model
{
    use BelongsToWorkspace; // Auto-scopes all queries to current workspace
}
```

### Transport-Aware Controllers

The same controller can serve HTTP requests and message-based commands:

```php
#[MessageController(transport: 'nats')]
final class OrderHandler
{
    #[CommandPattern(pattern: 'orders.create')]
    public function create(MessageEnvelope $envelope): array { ... }

    #[EventPattern(pattern: 'payments.completed')]
    public function onPayment(MessageEnvelope $envelope): void { ... }
}
```

Pluggable transports: NATS, RabbitMQ, SQS, Kafka, or `InMemoryTransport` for testing.

---

## Honest Comparison with Laravel

### What LatticePHP Does Better

| Area | Laravel | LatticePHP |
|---|---|---|
| Dependency visibility | Implicit (service providers, facades) | Explicit (module imports/exports) |
| Production performance | Reflection + route caching | Compiled attribute manifest, zero reflection |
| Durable workflows | Requires external Temporal or custom code | Built-in workflow engine |
| Multi-tenancy | Third-party packages (Tenancy for Laravel) | First-class workspace and tenant isolation |
| Microservices | Limited (queue-based only) | Typed message controllers with transport adapters |
| Input handling | Form Requests (mutable) | Readonly DTOs with attribute validation |
| Authorization model | Policies + middleware | `#[Authorize]` + `#[Roles]` + `#[Scopes]` + `#[Can]` attributes |
| Runtime support | PHP-FPM only (Octane is separate) | PHP-FPM + RoadRunner + OpenSwoole as first-class options |

### What Laravel Does Better

| Area | Why |
|---|---|
| Ecosystem size | Thousands of packages, massive community |
| Learning resources | Books, courses, Laracasts, tutorials |
| Frontend integration | Blade, Inertia, Livewire |
| Rapid prototyping | Artisan generators, scaffolding, starter kits |
| Hosting options | Forge, Vapor, countless managed platforms |
| Maturity | 10+ years of production use |

### What Is the Same

Both frameworks use the same Illuminate foundation:

- Eloquent ORM, relationships, scopes, casts -- identical
- Query Builder -- identical
- Database migrations and schema -- identical (use `Capsule::schema()` instead of `Schema::`)
- Cache, Queue, Events -- same drivers, same API
- Validation rules -- same rule logic, different syntax (attributes vs. arrays)
- `.env` files and `config/*.php` -- identical
- Collections, `Str::`, `Arr::`, Carbon -- identical

---

## When to Choose LatticePHP

Choose LatticePHP when:
- You need explicit module boundaries and dependency graphs
- You are building a microservice or service-oriented architecture
- You want durable workflows without external infrastructure
- You need multi-tenant data isolation built into the framework
- You want zero-reflection production performance
- Your project is backend-only (API, workers, services)

Choose Laravel when:
- You need full-stack with Blade/Inertia/Livewire
- You want maximum ecosystem compatibility
- You are prototyping rapidly and need scaffolding
- Your team is already experienced with Laravel conventions
- You need managed hosting (Forge, Vapor)

---

## Getting Started

```bash
composer create-project lattice/starter-api my-app
cd my-app
cp .env.example .env
php lattice serve
```

See the [Getting Started guide](getting-started.md) for a full walkthrough.
