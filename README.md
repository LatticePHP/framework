<div align="center">

# ⬡ LatticePHP

**The backend framework that thinks in modules, speaks in attributes, and orchestrates like Temporal.**

*Laravel engine underneath. NestJS architecture on top. Native durable execution in the middle.*

<br>

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-8892BF?style=flat-square&logo=php&logoColor=white)](https://www.php.net/releases/8.4/en.php)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-2%2C534_passing-22c55e?style=flat-square)](phpunit.xml)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level_Max-4338ca?style=flat-square)](phpstan.neon)
[![42 Packages](https://img.shields.io/badge/Packages-42-f59e0b?style=flat-square)](#-package-map-42-packages)

<br>

[**Get Started**](#-quick-start) · [**Documentation**](#-documentation) · [**Why LatticePHP?**](#-why-latticephp) · [**CRM Example**](#-the-crm-example) · [**Contributing**](CONTRIBUTING.md)

</div>

---

## ⚡ Why LatticePHP?

**Laravel is phenomenal.** But as your API grows past 50 endpoints, 10 services, and 3 microservices, you start fighting its conventions instead of leveraging them. Service providers become tangled. Middleware stacks become brittle. There is no first-class module system, no durable workflow engine, and no attribute-driven architecture.

**NestJS solved this for Node.** Modules with explicit imports/exports. Attribute-based routing. Guards, pipes, and interceptors that compose cleanly. But NestJS runs on Node — and your team writes PHP.

**LatticePHP bridges this gap.** It takes the Illuminate components you already know — Eloquent, Queue, Cache, Events, Validation — and wraps them in a modular, attribute-driven architecture inspired by NestJS. Then it adds something neither framework has: a **native durable execution engine** that gives you Temporal-class workflow orchestration without running a single external service.

The result is a framework where every feature lives in a self-contained module, every public API surface is an attribute, and long-running business processes survive crashes, deployments, and server restarts — all backed by the database and queue you already have.

---

## 🎯 At a Glance

```php
#[Module(
    imports: [DatabaseModule::class, JwtModule::forAccessTokens()],
    providers: [ContactService::class],
    controllers: [ContactController::class],
    exports: [ContactService::class],
)]
final class ContactModule {}
```

| Feature | How It Works |
|---------|-------------|
| **Modules** | `#[Module(imports: [...], controllers: [...])]` — explicit boundaries |
| **Routing** | `#[Controller('/api/contacts')]` + `#[Get('/:id')]` — zero config |
| **Guards** | `#[UseGuards(JwtAuthGuard::class)]` — composable auth |
| **DTOs** | `#[Body] CreateContactDto $dto` — auto-deserialization + validation |
| **Eloquent** | `Contact::filter($filter)->with('company')->paginate()` — full power |
| **Workspaces** | `#[BelongsToWorkspace]` — auto-scopes all queries |
| **Workflows** | Native durable execution with deterministic replay |
| **CQRS** | `CommandBus::dispatch(new CreateContact(...))` — clean separation |
| **Audit** | `#[Auditable]` — automatic change tracking |
| **Feature Flags** | `#[RequiresFeature('beta')]` — per-user/workspace rollout |
| **Testing** | `$this->postJson('/api/contacts', $data)->assertCreated()` |

---

## 🚀 Quick Start

**1. Create a new project**

```bash
composer create-project lattice/starter-api myapp
cd myapp
```

**2. Configure your environment**

```bash
cp .env.example .env    # SQLite by default — zero setup
```

**3. Run migrations and seed**

```bash
php lattice migrate
php lattice db:seed
```

**4. Start the server**

```bash
php lattice serve        # http://localhost:8000
```

**5. Test it**

```bash
curl http://localhost:8000/api/health
# {"status":"ok","timestamp":"2026-03-22T12:00:00Z"}

php lattice test         # Run the full test suite
```

### Scaffold a module in seconds

```bash
php lattice make:module Contacts --crud
```

This generates:
```
app/Modules/Contacts/
  ContactModule.php          # Module definition
  ContactController.php      # CRUD endpoints
  ContactService.php         # Business logic
  Dto/CreateContactDto.php   # Validated input
  Dto/UpdateContactDto.php   # Validated update
  ContactResource.php        # Response serializer
  Models/Contact.php         # Eloquent model
  tests/ContactApiTest.php   # API tests
```

---

## 🧱 Architecture

```
  ╔══════════════════════════════════════════════════════════════════╗
  ║  Layer 4 · YOUR APPLICATION                                      ║
  ║  ContactModule · DealModule · AuthModule · DashboardModule       ║
  ╠══════════════════════════════════════════════════════════════════╣
  ║  Layer 3 · LATTICEPHP FRAMEWORK                                  ║
  ║  module · compiler · pipeline · workflow · routing · auth        ║
  ║  openapi · observability · testing · devtools · microservices    ║
  ╠══════════════════════════════════════════════════════════════════╣
  ║  Layer 2 · ILLUMINATE COMPONENTS                                 ║
  ║  database · queue · cache · events · validation · console        ║
  ║  filesystem · mail · notifications · encryption · hashing        ║
  ╠══════════════════════════════════════════════════════════════════╣
  ║  Layer 1 · PHP 8.4+ RUNTIME                                      ║
  ║  Attributes · Readonly Props · Property Hooks · Enums · Fibers   ║
  ╚══════════════════════════════════════════════════════════════════╝
```

### The Request Lifecycle

```
Request → Router → Guards → Pipes → Interceptors → Controller → Response
            │         │        │          │              │
            │         │        │          │              └── Your business logic
            │         │        │          └── Cross-cutting concerns (logging, caching)
            │         │        └── Data transformation & validation
            │         └── Authentication & authorization checks
            └── Attribute-based route matching (#[Get], #[Post], ...)
```

Every step is an attribute. Every step composes. Every step works across HTTP, gRPC, and message transports.

---

## 📦 Package Map (42 packages)

| Category | Packages | Purpose |
|----------|----------|---------|
| **🔧 Core** | `contracts` · `core` · `compiler` · `module` · `pipeline` | Foundation: DI, modules, attribute compilation, execution pipeline |
| **🌐 HTTP & API** | `http` · `routing` · `validation` · `openapi` · `problem-details` · `jsonapi` · `rate-limit` | Request handling, routing, validation, API spec generation |
| **🔐 Auth** | `auth` · `jwt` · `pat` · `api-key` · `social` · `oauth` · `authorization` | JWT, personal access tokens, API keys, OAuth2/OIDC, RBAC |
| **💾 Data** | `database` · `cache` · `filesystem` · `events` · `scheduler` · `serializer` | Eloquent, caching, file storage, event dispatch, task scheduling |
| **⚡ Async** | `queue` · `microservices` · `workflow` · `workflow-store` | Job queues, message-driven microservices, durable workflows |
| **🔌 Transports** | `grpc` · `transport-nats` · `transport-rabbitmq` · `transport-sqs` · `transport-kafka` | Pluggable message transports for microservice communication |
| **🖥️ Runtime** | `roadrunner` · `openswoole` · `observability` | Long-running workers, tracing, metrics, structured logging |
| **🛠️ DX** | `devtools` · `testing` · `mail` · `notifications` · `http-client` | Generators, test harness, fakes, developer tooling |

Every package is independently installable via Composer. Use only what you need.

---

## 🏗️ The CRM Example

A **production-grade CRM** built entirely with LatticePHP — 7 modules, 37 routes, JWT auth, workspace isolation, and audit logging. Zero raw SQL. Zero manual PDO.

```
examples/crm/
  backend/
    app/Modules/
      Auth/          # Login, register, refresh tokens
      Contacts/      # Full CRUD + filtering + search
      Companies/     # Company management + relationships
      Deals/         # Pipeline stages, value tracking
      Activities/    # Calls, emails, meetings, tasks
      Notes/         # Rich-text notes on any entity
      Dashboard/     # Aggregations, pipeline metrics
```

### What it demonstrates

```php
// Contacts module — every framework feature in action
#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController
{
    #[Get('/')]
    public function index(Request $request): Response
    {
        $filter = QueryFilter::fromRequest($request->query);
        $contacts = Contact::filter($filter)
            ->with(['company'])
            ->paginate($filter->getPerPage());

        return ResponseFactory::paginated($contacts, ContactResource::class);
    }

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
    {
        $contact = $this->service->create($dto, $user);
        Log::info('Contact created', ['id' => $contact->id]);
        return ResponseFactory::created(['data' => ContactResource::make($contact)->toArray()]);
    }
}
```

**Key patterns used:** Module system, attribute routing, JWT + workspace guards, DTO validation, Eloquent with query filters, API resources, structured logging, and full test coverage.

---

## 🔐 Enterprise Features

LatticePHP ships with the features teams actually need in production:

| Feature | Description |
|---------|-------------|
| **RBAC** | Database-backed roles & permissions with `#[Authorize('contacts.create')]` |
| **Workspaces** | Multi-workspace with invitations, member roles, and `#[BelongsToWorkspace]` auto-scoping |
| **Multi-Tenancy** | Subdomain, header, or JWT-based tenant resolution with per-tenant DB/schema support |
| **Audit Logging** | `#[Auditable]` on models for automatic diff tracking, `#[AuditAction]` on controllers |
| **Feature Flags** | `#[RequiresFeature('beta')]` with per-user/workspace/tenant scoping and percentage rollout |
| **Circuit Breaker** | `#[CircuitBreaker]` for external service resilience with fallback methods |
| **CQRS** | `CommandBus` / `QueryBus` with `#[CommandHandler]` / `#[QueryHandler]` and async support |
| **Durable Workflows** | Temporal-class orchestration with deterministic replay, signals, queries, and compensation |

---

## ⚙️ Workflow Engine

A native durable execution engine — no external Temporal service required. Built on your existing database and queue.

```php
#[Workflow]
final class OrderFulfillmentWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): array
    {
        // Each activity is retried independently. State survives crashes.
        $payment = $ctx->executeActivity(
            PaymentActivity::class, 'charge',
            [$input['amount']],
            options: new ActivityOptions(retryPolicy: new RetryPolicy(maxAttempts: 3)),
        );

        $shipping = $ctx->executeActivity(
            ShippingActivity::class, 'ship',
            [$input['address']],
        );

        return ['payment' => $payment, 'shipping' => $shipping];
    }

    #[SignalMethod]
    public function cancel(): void { $this->cancelled = true; }

    #[QueryMethod]
    public function getStatus(): string { return $this->status; }
}
```

**What you get:** Deterministic replay, event sourcing, compensation/saga patterns, signals, queries, timers, and child workflows. All backed by the database and queue you already run.

---

## 🧪 Testing

LatticePHP is tested with **2,534 tests** across unit, integration, and end-to-end layers.

```
Package Tests          ~2,100 tests    Unit + integration per package
CRM Integration           ~200 tests    Full API workflow tests
E2E Tests                  ~150 tests    Cross-module, cross-package
Starter Kit Tests           ~84 tests    Verify project scaffolding
```

### Write tests the LatticePHP way

```php
final class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getModules(): array
    {
        return [ContactModule::class];
    }

    public function test_create_contact_with_valid_dto(): void
    {
        $this->actingAs($this->createUser());

        $response = $this->postJson('/api/contacts', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('contacts', ['email' => 'jane@example.com']);
    }

    public function test_list_contacts_requires_authentication(): void
    {
        $this->getJson('/api/contacts')->assertUnauthorized();
    }
}
```

**Fakes included:** `EventFake`, `QueueFake`, `MailFake`, `WorkflowFake`, `CacheFake` — test side effects without real infrastructure.

---

## 📚 Documentation

Comprehensive guides covering every aspect of the framework:

| Guide | Description |
|-------|-------------|
| [**Getting Started**](docs/guides/getting-started.md) | Installation, first project, first module |
| [**Why LatticePHP**](docs/guides/why-latticephp.md) | Philosophy, positioning, who it is for |
| [**Architecture**](docs/guides/architecture.md) | 4-layer architecture, boot sequence, request lifecycle |
| [**Modules**](docs/guides/modules.md) | Module system, imports/exports, dependency graph |
| [**Pipeline**](docs/guides/pipeline.md) | Guards, pipes, interceptors, exception filters |
| [**HTTP & API**](docs/guides/http-api.md) | Controllers, routing, DTOs, responses, pagination |
| [**Database**](docs/guides/database.md) | Eloquent integration, migrations, query filters |
| [**Auth**](docs/guides/auth.md) | JWT, PATs, API keys, OAuth2, social auth |
| [**Security**](docs/guides/security.md) | RBAC, policies, workspace isolation, tenancy |
| [**Workflows**](docs/guides/workflows.md) | Durable execution, activities, signals, queries |
| [**Microservices**](docs/guides/microservices.md) | Transport-aware controllers, NATS, RabbitMQ, SQS, Kafka |
| [**Testing**](docs/guides/testing.md) | TestCase, HTTP helpers, fakes, database assertions |
| [**Observability**](docs/guides/observability.md) | Logging, tracing, metrics, health checks |
| [**Runtime**](docs/guides/runtime.md) | PHP-FPM, RoadRunner, OpenSwoole |
| [**Package Authoring**](docs/guides/package-authoring.md) | Creating reusable LatticePHP packages |
| [**Migration from Laravel**](docs/guides/migration-from-laravel.md) | Step-by-step migration guide |

---

## 🔄 Migration from Laravel

Already on Laravel? Here is how the concepts map:

| Laravel | LatticePHP | Notes |
|---------|-----------|-------|
| `Route::get()` | `#[Get('/')]` | Attribute-based, zero config files |
| Service Providers | `#[Module]` | Explicit imports/exports, dependency graph |
| Middleware | Guards + Pipes + Interceptors | Separated by responsibility |
| Form Requests | `#[Body] CreateDto $dto` | Auto-deserialization + validation |
| `Auth::user()` | `#[CurrentUser] Principal $user` | Injected via parameter binding |
| `php artisan` | `php lattice` | Same Symfony Console underneath |
| `Model::factory()` | `Model::factory()` | Identical — same Eloquent |
| `$this->postJson()` | `$this->postJson()` | Same testing API |
| `Event::dispatch()` | `Event::dispatch()` | Same illuminate/events |
| Queued Jobs | `#[Async] CommandBus` or Queue | Same queue drivers, better patterns |

> **Full migration guide:** [docs/guides/migration-from-laravel.md](docs/guides/migration-from-laravel.md)

---

## 🛣️ Roadmap

### ✅ Shipped

- 42 framework packages with full test coverage
- Module system with attribute-based compilation
- HTTP kernel with guards, pipes, and interceptors
- JWT, PAT, API Key, OAuth2, and social authentication
- Native durable workflow engine
- Eloquent integration with query filters and search
- Multi-tenancy and workspace isolation
- CQRS with CommandBus/QueryBus
- Full CRM example application
- 4 starter kits (API, microservice, workflow, gRPC)
- 16 documentation guides

### 🔜 Next

- GitHub Actions CI/CD pipeline with PHP 8.4/8.5 matrix
- Docker production setup with RoadRunner
- Packagist publishing for all 42 packages
- Community plugins ecosystem
- Visual workflow designer

---

## 🤝 Contributing

We welcome contributions of all kinds. See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:

- Setting up the development environment
- Running the test suite (`php lattice test` or `vendor/bin/phpunit`)
- Code style (PHP CS Fixer) and static analysis (PHPStan level max)
- Submitting pull requests

```bash
# Clone and set up
git clone https://github.com/latticephp/lattice.git
cd lattice
composer install

# Run quality checks
vendor/bin/phpunit                    # Tests
vendor/bin/phpstan analyse            # Static analysis
vendor/bin/php-cs-fixer fix --dry-run # Code style
```

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

Copyright (c) 2026 LatticePHP

---

<div align="center">

**Built with 🧱 by the LatticePHP team**

*42 packages · 2,534 tests · 16 guides · 1 vision*

[Documentation](docs/guides/getting-started.md) · [CRM Example](examples/crm/) · [Starter Kit](starters/api/) · [Report Issue](https://github.com/latticephp/lattice/issues)

</div>
