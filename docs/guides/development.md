# LatticePHP Development Guide

This guide is for framework developers who are working on LatticePHP itself — not for application developers building with LatticePHP. If you're building an application, see the [Getting Started](getting-started.md) guide instead.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [The Monorepo](#the-monorepo)
- [How Packages Work Together](#how-packages-work-together)
- [The Request Lifecycle](#the-request-lifecycle)
- [The Compilation Pipeline](#the-compilation-pipeline)
- [The Module System](#the-module-system)
- [The Pipeline Executor](#the-pipeline-executor)
- [The Workflow Engine](#the-workflow-engine)
- [Working with Illuminate Components](#working-with-illuminate-components)
- [Adding a New Package](#adding-a-new-package)
- [Adding a New Attribute](#adding-a-new-attribute)
- [Adding a New Guard](#adding-a-new-guard)
- [Adding a New Transport](#adding-a-new-transport)
- [Adding CLI Commands](#adding-cli-commands)
- [The Testing Harness](#the-testing-harness)
- [Debugging Common Issues](#debugging-common-issues)
- [Performance Considerations](#performance-considerations)
- [The Split Pipeline](#the-split-pipeline)
- [Release Checklist](#release-checklist)

---

## Architecture Overview

LatticePHP is built on three pillars:

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                         │
│  Modules, Controllers, DTOs, Services, Repositories         │
├─────────────────────────────────────────────────────────────┤
│                   ARCHITECTURE LAYER                         │
│  Module System, Compiler, Pipeline, Guards, Interceptors     │
│  Attributes, CQRS, Feature Flags, Circuit Breaker           │
├─────────────────────────────────────────────────────────────┤
│                    ENGINE LAYER                              │
│  illuminate/database, illuminate/queue, illuminate/cache     │
│  illuminate/events, illuminate/validation, illuminate/mail   │
│  symfony/http-foundation, symfony/console                    │
├─────────────────────────────────────────────────────────────┤
│                    RUNTIME LAYER                             │
│  PHP-FPM | RoadRunner | OpenSwoole                          │
└─────────────────────────────────────────────────────────────┘
```

**Key insight:** We never reimplement what Illuminate already does well. We reshape it with a modular architecture, attribute-driven configuration, and pipeline-based request handling.

### Package Categories

| Category | Packages | Purpose |
|----------|----------|---------|
| **Foundation** | contracts, core, compiler, module | The kernel — boot, DI, module graph, attribute discovery |
| **HTTP** | http, routing, pipeline, validation, openapi, problem-details, rate-limit, jsonapi | Request → Response lifecycle |
| **Auth** | auth, jwt, pat, api-key, oauth, social, authorization | Authentication and authorization |
| **Data** | database, cache, filesystem, events, mail, notifications | Illuminate component wrappers |
| **Async** | queue, scheduler | Background processing |
| **Workflow** | workflow, workflow-store | Durable execution engine |
| **Transport** | microservices, grpc, transport-nats, transport-rabbitmq, transport-sqs, transport-kafka | Message transport abstraction |
| **Runtime** | roadrunner, openswoole | Long-running process adapters |
| **Enterprise** | authorization (CASL), observability | Production-grade features |
| **DX** | devtools, testing, serializer, http-client | Developer experience |

---

## The Monorepo

All 42 packages live in one Git repository. This is deliberate:

**Why monorepo:**
- Atomic commits across package boundaries
- Single CI pipeline for all packages
- No version matrix hell during development
- Easier to discover and navigate code
- Refactoring across packages is one commit

**How it works in practice:**
- Root `composer.json` registers all package namespaces in `autoload.psr-4`
- Root `phpunit.xml` defines test suites for every package
- Root `phpstan.neon` analyzes all package `src/` directories
- `.php-cs-fixer.dist.php` formats all package code
- `.github/workflows/split.yml` splits each package to its own read-only repo on push

**Development workflow:**
```bash
# You work in the monorepo
cd framework/

# Edit any package directly
vim packages/workflow/src/Runtime/WorkflowContext.php

# Tests run from root
vendor/bin/phpunit packages/workflow/tests/

# One commit can touch multiple packages
git add packages/workflow/ packages/contracts/
git commit -m "[workflow] Add activity timeout support"
```

---

## How Packages Work Together

### The Dependency Graph

```
                        ┌──────────┐
                        │ contracts│ (interfaces only, zero deps)
                        └────┬─────┘
                             │
                    ┌────────┼────────┐
                    │        │        │
               ┌────┴──┐ ┌──┴───┐ ┌──┴──────┐
               │ core  │ │module│ │compiler │
               └───┬───┘ └──┬───┘ └────┬────┘
                   │        │          │
            ┌──────┼────────┼──────────┤
            │      │        │          │
      ┌─────┴──┐ ┌┴────┐ ┌─┴──────┐ ┌┴────────┐
      │pipeline│ │http │ │routing │ │validation│
      └────────┘ └─────┘ └────────┘ └──────────┘
            │
     ┌──────┼──────────┐
     │      │          │
  ┌──┴─┐ ┌─┴──┐ ┌─────┴────┐
  │auth│ │jwt │ │authorize │
  └────┘ └────┘ └──────────┘
```

### Communication Between Packages

Packages communicate through **contracts** (interfaces), never through direct class references:

```php
// packages/auth/src/JwtAuthGuard.php
// Depends on GuardInterface from contracts, not on a specific implementation
namespace Lattice\Auth;

use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Auth\PrincipalInterface;

final class JwtAuthGuard implements GuardInterface
{
    public function canActivate(ExecutionContext $context): bool
    {
        // Uses PrincipalInterface, not a concrete User class
        $principal = $this->extractPrincipal($context);
        $context->setPrincipal($principal);
        return $principal !== null;
    }
}
```

---

## The Request Lifecycle

When an HTTP request arrives, this is the exact execution path:

```
Client → public/index.php → Application::handleRequest()
  │
  ├─ 1. CORS preflight check (if OPTIONS, return immediately)
  │
  ├─ 2. Router::match($request)
  │     └─ Static routes checked first, then parameterized
  │     └─ Returns RouteMatch (controller, method, params, guards, pipes)
  │
  ├─ 3. Guard Resolution
  │     ├─ Global guards (from config)
  │     ├─ Module guards (from #[Module])
  │     ├─ Controller guards (from #[UseGuards] on class)
  │     └─ Method guards (from #[UseGuards] on method)
  │     └─ All resolved from DI container
  │
  ├─ 4. PipelineExecutor::execute()
  │     │
  │     ├─ 4a. Guards::canActivate() — all must return true
  │     │     └─ Sets PrincipalInterface on ExecutionContext
  │     │
  │     ├─ 4b. Interceptors::before() — in order
  │     │
  │     ├─ 4c. Pipes::transform() — in order
  │     │     └─ Validation, transformation, coercion
  │     │
  │     ├─ 4d. Parameter Resolution
  │     │     ├─ #[Body] → DtoMapper → illuminate/validation
  │     │     ├─ #[Query] → query string extraction + coercion
  │     │     ├─ #[Param] → route param + optional model binding
  │     │     ├─ #[Header] → header extraction
  │     │     ├─ #[CurrentUser] → PrincipalInterface from context
  │     │     └─ Request/PrincipalInterface → auto-inject
  │     │
  │     ├─ 4e. Controller Method Invocation
  │     │     └─ $controller->$method(...$resolvedParams)
  │     │
  │     ├─ 4f. Interceptors::after() — in reverse order
  │     │
  │     └─ 4g. Response Serialization
  │           └─ Array/object → JSON → Response
  │
  ├─ 5. Exception Handling (if anything threw)
  │     └─ ExceptionFilter → ProblemDetails (RFC 9457)
  │
  └─ 6. Response → Client
```

**Key files:**
- `packages/core/src/Application.php` — `handleRequest()`
- `packages/http/src/HttpKernel.php` — wires routing to pipeline
- `packages/pipeline/src/PipelineExecutor.php` — guard/interceptor/pipe execution
- `packages/http/src/ParameterResolver.php` — `#[Body]`, `#[Query]`, etc.
- `packages/routing/src/Router.php` — route matching
- `packages/problem-details/src/ProblemDetailsFilter.php` — error responses

---

## The Compilation Pipeline

In production, LatticePHP compiles all attribute metadata once and caches it:

```
Boot → Compiler::compile()
  │
  ├─ 1. Scan all registered module classes
  │
  ├─ 2. For each module:
  │     ├─ Read #[Module] attribute
  │     ├─ Discover imports (recursive DFS)
  │     ├─ Discover controllers
  │     ├─ Discover providers
  │     └─ Discover exports
  │
  ├─ 3. For each controller:
  │     ├─ Read #[Controller] attribute (prefix)
  │     ├─ Read #[UseGuards] on class
  │     ├─ For each method:
  │     │   ├─ Read #[Get], #[Post], etc. (HTTP method + path)
  │     │   ├─ Read #[UseGuards] on method
  │     │   ├─ Read parameter attributes (#[Body], #[Query], etc.)
  │     │   └─ Build ParameterBinding for each param
  │     └─ Build RouteDefinition
  │
  ├─ 4. Build ModuleGraph (dependency tree)
  │
  ├─ 5. Build RouteCollection
  │
  └─ 6. Cache everything (manifest file or opcache)
       └─ In production: no reflection on any request
```

**Key files:**
- `packages/compiler/src/AttributeCompiler.php` — attribute scanning
- `packages/compiler/src/ModuleGraphBuilder.php` — module dependency resolution
- `packages/compiler/src/Manifest.php` — cached compilation result

**Rule:** Production code must NEVER call `ReflectionClass`, `ReflectionMethod`, or `ReflectionAttribute` outside the compiler. All metadata is pre-resolved.

---

## The Module System

Every feature lives in a module. Modules are PHP classes with the `#[Module]` attribute:

```php
#[Module(
    imports: [DatabaseModule::class, AuthModule::class],
    controllers: [UserController::class, ProfileController::class],
    providers: [
        UserService::class,
        UserRepository::class,
        new ValueProvider('user.config', ['max_per_page' => 50]),
    ],
    exports: [UserService::class],
)]
final class UserModule {}
```

### Module Resolution

```
AppModule
  ├─ imports: [UserModule, ProductModule]
  │
  UserModule
  │ ├─ imports: [DatabaseModule, AuthModule]
  │ ├─ controllers: [UserController]
  │ ├─ providers: [UserService, UserRepository]
  │ └─ exports: [UserService]
  │
  ProductModule
  │ ├─ imports: [DatabaseModule, UserModule]  ← can use UserService (it's exported)
  │ ├─ controllers: [ProductController]
  │ └─ providers: [ProductService]
  │
  DatabaseModule (shared, imported by both)
  │ └─ providers: [DatabaseManager, ConnectionFactory]
  │
  AuthModule
    └─ providers: [JwtAuthGuard, TokenService]
```

**Module rules:**
1. A module can only use providers from modules it imports (+ what those export)
2. Providers not in `exports` are private to the module
3. Modules are singletons — importing the same module twice shares the instance
4. Circular imports are forbidden (compiler error)
5. The `AppModule` (root) sees everything

### How Modules Map to DI

```php
// When UserModule is loaded:
$container->singleton(UserService::class);
$container->singleton(UserRepository::class);

// UserService is exported → other modules can inject it
// UserRepository is NOT exported → only available within UserModule
```

**Key files:**
- `packages/module/src/Attributes/Module.php` — attribute definition
- `packages/module/src/ModuleLoader.php` — loads modules into DI
- `packages/module/src/ModuleRegistry.php` — tracks loaded modules

---

## The Pipeline Executor

The pipeline is the core execution mechanism. It processes requests through a chain of guards, interceptors, pipes, and filters:

```
Request ──→ [Guard₁] ──→ [Guard₂] ──→ [Interceptor₁.before()]
                                          │
                                     [Pipe₁.transform()]
                                          │
                                     [Pipe₂.transform()]
                                          │
                                     Controller::method()
                                          │
                                     [Interceptor₁.after()]
                                          │
                                       Response
```

### Component Roles

| Component | Interface | Purpose | Fails with |
|-----------|-----------|---------|------------|
| **Guard** | `GuardInterface` | Can this request proceed? (auth, rate limit) | `ForbiddenException` (403) |
| **Interceptor** | `InterceptorInterface` | Before/after logic (logging, caching, timing) | Any exception |
| **Pipe** | `PipeInterface` | Transform input (validation, coercion, sanitization) | `ValidationException` (422) |
| **Filter** | `ExceptionFilterInterface` | Transform exceptions into responses | N/A (last resort) |

### Execution Order

Guards, interceptors, and pipes are applied in this order:
1. Global (registered in config)
2. Module-level (from `#[Module]`)
3. Controller-level (from `#[UseGuards]` on class)
4. Method-level (from `#[UseGuards]` on method)

Interceptors' `after()` methods run in **reverse** order (LIFO).

**Key files:**
- `packages/pipeline/src/PipelineExecutor.php`
- `packages/pipeline/src/Contracts/GuardInterface.php`
- `packages/pipeline/src/Contracts/InterceptorInterface.php`
- `packages/pipeline/src/Contracts/PipeInterface.php`

---

## The Workflow Engine

The workflow engine is a Temporal-class durable execution system built entirely on the framework's own database + queue + scheduler:

### Architecture

```
┌──────────────────────────────────────────────────┐
│                  WorkflowRuntime                  │
│  ├─ Deterministic Replay Engine                  │
│  ├─ Activity Executor (via queue)                │
│  ├─ Timer Scheduler                              │
│  └─ Signal/Query Handler                         │
├──────────────────────────────────────────────────┤
│               WorkflowContext                     │
│  ├─ executeActivity(name, input)                 │
│  ├─ executeChildWorkflow(class, input)           │
│  ├─ sleep(duration)                              │
│  ├─ waitForSignal(name)                          │
│  └─ query(name) → result                         │
├──────────────────────────────────────────────────┤
│               EventStore                          │
│  ├─ InMemoryEventStore (testing)                 │
│  └─ DatabaseEventStore (production)              │
│      └─ workflow_events table                     │
│         (workflow_id, sequence, type, payload)    │
└──────────────────────────────────────────────────┘
```

### Deterministic Replay

This is the most critical concept in the workflow engine:

1. A workflow function runs from the beginning every time it's resumed
2. When it hits `executeActivity()`, the engine checks the event history
3. If the activity result is in the history → return the cached result (replay)
4. If not → actually execute the activity (live mode)
5. The transition from replay to live is seamless

```php
// This workflow function may run 3+ times (once per activity completion)
// But each activity only executes ONCE — replay returns cached results
public function handle(WorkflowContext $ctx): string
{
    $validated = $ctx->executeActivity('validate', $input);    // Replay or live
    $charged   = $ctx->executeActivity('charge', $validated);  // Replay or live
    $shipped   = $ctx->executeActivity('ship', $charged);      // Replay or live
    return $shipped;
}
```

### Key Bug That Was Fixed

The replay-to-live transition had a critical bug: when the event history was exhausted mid-workflow (e.g., after 2 of 3 activities), `ReplayCaughtUpException` propagated uncaught and killed the workflow instead of switching to live execution. This was fixed by catching the exception in `executeActivity()`, `sleep()`, and `executeChildWorkflow()`.

**Key files:**
- `packages/workflow/src/Runtime/WorkflowRuntime.php` — orchestrates execution
- `packages/workflow/src/Runtime/WorkflowContext.php` — the API workflows use
- `packages/workflow/src/Events/` — event types
- `packages/workflow/src/Compensation/CompensationScope.php` — saga pattern
- `packages/workflow-store/src/DatabaseEventStore.php` — persistent storage
- `packages/workflow/tests/Integration/WorkflowStressTest.php` — 38 exhaustive tests

---

## Working with Illuminate Components

LatticePHP uses Illuminate components as the engine. Here's how to integrate them:

### Direct Usage (Preferred)

```php
// Use Illuminate classes directly — don't wrap them
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class User extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
}
```

### When to Wrap

Only create wrappers when LatticePHP needs to add behavior that Illuminate doesn't provide:

```php
// Wrapper justified — adds workspace scoping that Illuminate doesn't have
namespace Lattice\Database;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    // Only add what Illuminate doesn't provide
    // Don't override existing methods unless necessary
}
```

### Illuminate Component Map

| Illuminate Package | LatticePHP Package | How We Use It |
|---|---|---|
| `illuminate/container` | `core` | DI container, used directly |
| `illuminate/database` | `database` | Eloquent, Query Builder, Migrations, used directly |
| `illuminate/validation` | `validation` | Validates `#[Body]` DTOs, used directly |
| `illuminate/queue` | `queue` | Job dispatch and processing, used directly |
| `illuminate/cache` | `cache` | Cache drivers, used directly |
| `illuminate/events` | `events` | Event dispatch, `#[Listener]`, used directly |
| `illuminate/console` | `core` (CLI) | Symfony Console underneath, used for `bin/lattice` |
| `illuminate/filesystem` | `filesystem` | Flysystem integration, used directly |
| `illuminate/mail` | `mail` | Email sending, used directly |
| `illuminate/notifications` | `notifications` | Multi-channel notifications, used directly |

**Rule:** If Illuminate provides it and it works, use it. Only wrap when adding LatticePHP-specific behavior (modules, attributes, pipeline integration).

---

## Adding a New Package

### Step 1: Create the Structure

```bash
mkdir -p packages/my-feature/src/{Attributes,Contracts,Exceptions,Support}
mkdir -p packages/my-feature/tests/{Unit,Integration}
```

### Step 2: Create `composer.json`

```json
{
    "name": "lattice/my-feature",
    "description": "What this package does in one sentence",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "lattice/contracts": "^1.0"
    },
    "autoload": {
        "psr-4": { "Lattice\\MyFeature\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Lattice\\MyFeature\\Tests\\": "tests/" }
    },
    "extra": {
        "branch-alias": { "dev-main": "1.0.x-dev" }
    }
}
```

### Step 3: Register in Root Files

**Root `composer.json`** — add to `autoload.psr-4` and `autoload-dev.psr-4`:
```json
"Lattice\\MyFeature\\": "packages/my-feature/src/",
"Lattice\\MyFeature\\Tests\\": "packages/my-feature/tests/",
```

**Root `phpunit.xml`** — add a test suite:
```xml
<testsuite name="MyFeature">
    <directory>packages/my-feature/tests</directory>
</testsuite>
```

**`.github/workflows/split.yml`** — add to the matrix:
```yaml
- { local: 'packages/my-feature', remote: 'my-feature' }
```

### Step 4: Define Contracts First

Add interfaces to `packages/contracts/src/MyFeature/`:

```php
namespace Lattice\Contracts\MyFeature;

interface MyServiceInterface
{
    public function doThing(string $input): Result;
}
```

### Step 5: Write Tests First (TDD)

```php
namespace Lattice\MyFeature\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lattice\MyFeature\MyService;

final class MyServiceTest extends TestCase
{
    public function test_do_thing_returns_result(): void
    {
        $service = new MyService();
        $result = $service->doThing('input');
        $this->assertInstanceOf(Result::class, $result);
    }
}
```

### Step 6: Implement

### Step 7: Create the Split Repo

```bash
gh repo create LatticePHP/my-feature --public \
  --description "LatticePHP my-feature package (read-only split)"
```

---

## Adding a New Attribute

### Step 1: Define the Attribute

```php
// packages/my-feature/src/Attributes/MyAttribute.php
namespace Lattice\MyFeature\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class MyAttribute
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0,
        public readonly bool $enabled = true,
    ) {}
}
```

### Step 2: Register in the Compiler

Add discovery logic to the compiler so it picks up the attribute:

```php
// In the compiler's attribute scanner
$attributes = $reflection->getAttributes(MyAttribute::class);
foreach ($attributes as $attr) {
    $instance = $attr->newInstance();
    // Store in the compiled manifest
}
```

### Step 3: Write Tests

Test both the attribute itself and its effect on the system:

```php
// Test the attribute is valid PHP
public function test_my_attribute_can_be_instantiated(): void
{
    $attr = new MyAttribute(name: 'test', priority: 5);
    $this->assertSame('test', $attr->name);
    $this->assertSame(5, $attr->priority);
}

// Test the attribute is discovered by the compiler
public function test_my_attribute_is_discovered_on_class(): void
{
    $compiler = new AttributeCompiler();
    $result = $compiler->scan(ClassWithMyAttribute::class);
    $this->assertCount(1, $result->myAttributes);
}
```

---

## Adding a New Guard

```php
// packages/auth/src/Guards/ApiKeyGuard.php
namespace Lattice\Auth\Guards;

use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\ExecutionContext;

final class ApiKeyGuard implements GuardInterface
{
    public function __construct(
        private readonly ApiKeyRepository $keys,
    ) {}

    public function canActivate(ExecutionContext $context): bool
    {
        $request = $context->getRequest();
        $apiKey = $request->headers->get('X-Api-Key');

        if ($apiKey === null) {
            return false;
        }

        $key = $this->keys->findByKey($apiKey);

        if ($key === null || $key->isExpired()) {
            return false;
        }

        $context->setPrincipal($key->getOwner());
        return true;
    }
}
```

**Usage:**
```php
#[Controller('/api/external')]
#[UseGuards(ApiKeyGuard::class)]
final class ExternalApiController { ... }
```

---

## Adding a New Transport

Transport adapters follow the adapter pattern:

```php
// packages/transport-redis/src/RedisTransport.php
namespace Lattice\Transport\Redis;

use Lattice\Contracts\Microservices\TransportInterface;
use Lattice\Contracts\Microservices\MessageInterface;

final class RedisTransport implements TransportInterface
{
    public function __construct(
        private readonly RedisConnection $redis,
        private readonly string $channel,
    ) {}

    public function send(MessageInterface $message): void
    {
        $this->redis->publish($this->channel, serialize($message));
    }

    public function receive(): ?MessageInterface
    {
        $data = $this->redis->subscribe($this->channel);
        return $data ? unserialize($data) : null;
    }
}
```

**Rules:**
- Transport adapters depend ONLY on `Lattice\Contracts\Microservices\`
- They never import other transport adapters
- They never import HTTP, routing, or other non-transport packages

---

## Adding CLI Commands

CLI commands use Symfony Console (via `illuminate/console`):

```php
// packages/devtools/src/Commands/MakeModuleCommand.php
namespace Lattice\DevTools\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModuleCommand extends Command
{
    protected static $defaultName = 'make:module';
    protected static $defaultDescription = 'Create a new module';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Module name')
            ->addOption('crud', null, InputOption::VALUE_NONE, 'Include CRUD scaffold');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        // Generate module files...
        $output->writeln("<info>Module {$name} created successfully.</info>");
        return Command::SUCCESS;
    }
}
```

Register commands in the Application boot:
```php
$application->add(new MakeModuleCommand());
```

---

## The Testing Harness

The `testing` package provides utilities for testing LatticePHP applications:

```php
use Lattice\Testing\TestCase;
use Lattice\Testing\RefreshDatabase;

final class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['name' => 'John Doe']);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    }

    public function test_auth_with_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/users');
        $response->assertStatus(200);
    }
}
```

### Fakes

```php
// Mail
Mail::fake();
// ... code that sends mail ...
Mail::assertSent(WelcomeEmail::class);

// Notifications
Notification::fake();
// ... code that sends notification ...
Notification::assertSentTo($user, InvoiceNotification::class);

// Events
Event::fake();
// ... code that dispatches event ...
Event::assertDispatched(UserCreated::class);

// Queue
Queue::fake();
// ... code that dispatches job ...
Queue::assertPushed(ProcessPayment::class);
```

---

## Debugging Common Issues

### "Class not found" errors

1. Check the namespace matches the file path
2. Run `composer dump-autoload`
3. Verify the package is registered in root `composer.json` autoload

### "Cannot resolve dependency" errors

1. Check the class is registered as a provider in a module
2. Check the module is imported by the consuming module
3. Check the DI container binding is correct

### Guards not executing

1. Verify `#[UseGuards]` is on the controller/method
2. Check the guard class implements `GuardInterface`
3. Ensure the guard is resolvable from the DI container
4. Check the HttpKernel is merging global + route guards

### Workflow replay issues

1. Ensure workflow functions are **deterministic** — no `rand()`, `time()`, or external I/O
2. All side effects must go through `$ctx->executeActivity()`
3. Check the event store has the expected events: `$store->getEvents($workflowId)`
4. Verify sequence numbers don't have gaps or collisions

### "Method not allowed" / wrong route matched

1. Check static vs parameterized route priority (static wins)
2. Verify HTTP method matches (`#[Get]` vs `#[Post]`)
3. Check the route prefix from `#[Controller('/prefix')]`
4. Run route listing: `php lattice route:list`

---

## Performance Considerations

### Hot Path Rules

1. **Never reflect on the hot path.** All attribute metadata must be compiled and cached.
2. **Never instantiate the DI container per-request in long-running workers.** The container is built once at boot and reused.
3. **Never create new database connections per-request.** Use connection pooling.
4. **Cache aggressively.** Route matching, module resolution, guard resolution — all cached.

### Memory in Long-Running Workers

Under RoadRunner/OpenSwoole, the application lives in memory across requests:

```php
// BAD — leaks memory across requests
final class RequestLogger
{
    private array $logs = []; // Grows forever!

    public function log(string $msg): void
    {
        $this->logs[] = $msg;
    }
}

// GOOD — reset per request
final class RequestLogger
{
    private array $logs = [];

    public function log(string $msg): void
    {
        $this->logs[] = $msg;
    }

    public function reset(): void
    {
        $this->logs = [];
    }
}
```

### Database Query Optimization

Use Eloquent's eager loading to prevent N+1:

```php
// BAD — N+1 queries
$users = User::all();
foreach ($users as $user) {
    echo $user->workspace->name; // Query per user!
}

// GOOD — eager loaded
$users = User::with('workspace')->get();
foreach ($users as $user) {
    echo $user->workspace->name; // No extra queries
}
```

---

## The Split Pipeline

When code is pushed to `main` in the framework monorepo:

```
Push to main
  │
  ├─ GitHub Actions: split.yml triggers
  │
  ├─ Matrix: 42 parallel jobs (one per package)
  │
  ├─ Each job:
  │   ├─ Checkout framework with full history (fetch-depth: 0)
  │   ├─ Run danharrin/monorepo-split-github-action
  │   │   ├─ Clone target repo (e.g., LatticePHP/workflow)
  │   │   ├─ Copy packages/workflow/ contents
  │   │   ├─ Commit and push to target repo's main branch
  │   │   └─ If tag push: also push the tag
  │   └─ Done
  │
  └─ Result: all 42 read-only repos updated
```

**Important:** The split uses `SPLIT_TOKEN` (a PAT with `repo` scope). `GITHUB_TOKEN` can't write to other repos.

---

## Release Checklist

Before tagging a release:

- [ ] All tests pass: `composer test`
- [ ] PHPStan clean: `composer analyze`
- [ ] Code style clean: `composer format:check`
- [ ] CHANGELOG.md updated
- [ ] Cross-package dependency versions pinned
- [ ] Docker tests pass: `docker compose run --rm test`
- [ ] Documentation updated for new features
- [ ] No TODO/FIXME/HACK in new code
- [ ] Breaking changes documented in upgrade guide
- [ ] Starter kits updated to require new version

**Tagging:**
```bash
git tag v1.1.0
git push origin v1.1.0
# split.yml automatically pushes tags to all 42 repos
# Packagist automatically picks up new versions
```
