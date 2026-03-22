# Contributing to LatticePHP

Thank you for considering contributing to LatticePHP. This guide covers everything you need to know — from setting up your development environment to getting your pull request merged.

LatticePHP is a **monorepo**. All 42 packages live in `packages/` within the [LatticePHP/framework](https://github.com/LatticePHP/framework) repository. Individual package repos (e.g., `LatticePHP/core`, `LatticePHP/workflow`) are **read-only mirrors** — never open PRs against them. All contributions go to `LatticePHP/framework`.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
  - [Reporting Bugs](#reporting-bugs)
  - [Suggesting Features](#suggesting-features)
  - [Writing Documentation](#writing-documentation)
  - [Submitting Code](#submitting-code)
- [Development Environment Setup](#development-environment-setup)
  - [Prerequisites](#prerequisites)
  - [Clone and Install](#clone-and-install)
  - [Docker Setup](#docker-setup)
  - [IDE Configuration](#ide-configuration)
- [Repository Structure](#repository-structure)
- [Branching Strategy](#branching-strategy)
- [Coding Standards](#coding-standards)
  - [PHP Version and Features](#php-version-and-features)
  - [Type System](#type-system)
  - [Class Design](#class-design)
  - [Naming Conventions](#naming-conventions)
  - [Namespace Conventions](#namespace-conventions)
  - [Attribute Design](#attribute-design)
  - [Code Style (PHP CS Fixer)](#code-style-php-cs-fixer)
  - [Static Analysis (PHPStan)](#static-analysis-phpstan)
- [Testing](#testing)
  - [Test-Driven Development](#test-driven-development)
  - [Test Organization](#test-organization)
  - [Test Naming](#test-naming)
  - [Running Tests](#running-tests)
  - [Writing Good Tests](#writing-good-tests)
  - [Testing Attributes and Metadata](#testing-attributes-and-metadata)
  - [Integration Testing](#integration-testing)
- [Package Development](#package-development)
  - [Creating a New Package](#creating-a-new-package)
  - [Package Directory Structure](#package-directory-structure)
  - [Package composer.json](#package-composerjson)
  - [Dependency Rules](#dependency-rules)
  - [Cross-Package Dependencies](#cross-package-dependencies)
- [Architecture Principles](#architecture-principles)
  - [Immutable Guardrails](#immutable-guardrails)
  - [Dependency Direction](#dependency-direction)
  - [Contract-First Design](#contract-first-design)
  - [Attribute-Driven API](#attribute-driven-api)
  - [Transport Awareness](#transport-awareness)
- [Pull Request Process](#pull-request-process)
  - [Before You Submit](#before-you-submit)
  - [PR Title and Description](#pr-title-and-description)
  - [Review Process](#review-process)
  - [After Merge](#after-merge)
- [Architecture Decision Records](#architecture-decision-records)
- [Request for Comments (RFCs)](#request-for-comments-rfcs)
- [Release Process](#release-process)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Community](#community)
- [License](#license)

---

## Code of Conduct

All contributors must follow our [Code of Conduct](CODE_OF_CONDUCT.md). Be respectful, constructive, and inclusive. We are building something ambitious together and every contributor matters.

---

## How Can I Contribute?

### Reporting Bugs

Found a bug? Open a [GitHub issue](https://github.com/LatticePHP/framework/issues/new) with:

1. **Title:** Clear, concise summary (e.g., "Router fails to match parameterized routes with dots")
2. **LatticePHP version:** The exact version (`composer show lattice/core | grep versions`)
3. **PHP version:** Output of `php -v`
4. **Operating system:** e.g., Ubuntu 24.04, macOS 15, Windows 11
5. **Runtime:** PHP-FPM, RoadRunner, or OpenSwoole
6. **Steps to reproduce:** Minimal code example that triggers the bug
7. **Expected behavior:** What you expected to happen
8. **Actual behavior:** What actually happened (include full error messages and stack traces)
9. **Possible cause:** If you have ideas about what might be wrong (optional)

**Good bug report:**
```
Title: #[Body] parameter binding fails for DTOs with readonly promoted properties

LatticePHP: 1.0.0
PHP: 8.4.19
OS: Ubuntu 24.04
Runtime: PHP-FPM

Steps to reproduce:
1. Create a DTO with readonly promoted constructor properties
2. Use #[Body] to bind it in a controller method
3. Send a POST request with JSON body

Expected: DTO is hydrated with request data
Actual: TypeError — Cannot initialize readonly property outside constructor

Stack trace:
[paste full trace here]
```

**Bad bug report:**
```
Title: Body binding broken
Description: It doesn't work
```

### Suggesting Features

For feature requests, open an issue with the **"feature request"** label:

1. **Problem statement:** What problem does this solve? What use case does it address?
2. **Proposed solution:** How should it work? Include code examples of the desired API.
3. **Alternatives considered:** What other approaches did you consider and why did you reject them?
4. **Breaking changes:** Does this require breaking existing APIs?
5. **Scope:** Which packages would this affect?

For **large features** (new packages, architectural changes, new runtime support), write an RFC first — see [Request for Comments](#request-for-comments-rfcs).

### Writing Documentation

Documentation improvements are always welcome:

- Fix typos and grammar in existing docs
- Add missing code examples
- Improve explanations of complex concepts
- Add guides for common use cases
- Translate documentation

Documentation lives in:
- `docs/guides/` — User guides (Markdown)
- `website/guide/` — VitePress documentation site
- Package `README.md` files — Package-specific docs
- Inline PHPDoc — Code-level documentation

### Submitting Code

Code contributions include:

- **Bug fixes:** Fix a reported issue
- **Features:** Implement an approved feature request
- **Tests:** Increase test coverage
- **Refactoring:** Improve code quality without changing behavior
- **Performance:** Optimize hot paths

All code contributions must include tests and pass all CI checks.

---

## Development Environment Setup

### Prerequisites

| Requirement | Minimum Version | Recommended |
|-------------|----------------|-------------|
| PHP | 8.4.0 | 8.4.19+ |
| Composer | 2.7+ | Latest |
| Git | 2.30+ | Latest |
| SQLite | 3.35+ | Latest |
| Docker (optional) | 24.0+ | Latest |

**Required PHP extensions:**

```
bcmath, ctype, curl, dom, fileinfo, intl, json, mbstring,
openssl, pcntl, pdo, pdo_sqlite, pdo_mysql, pdo_pgsql,
sodium, tokenizer, xml, zip
```

**Optional PHP extensions:**

```
redis       — For Redis cache/queue/session drivers
swoole      — For OpenSwoole runtime
grpc        — For gRPC transport
protobuf    — For gRPC serialization
```

### Clone and Install

```bash
# Clone the monorepo
git clone git@github.com:LatticePHP/framework.git
cd framework

# Install all dependencies
composer install

# Verify everything works
composer test        # Run full test suite
composer analyze     # Run PHPStan static analysis
composer format:check # Check code style
```

### Docker Setup

For a consistent environment across all platforms:

```bash
# Build the framework image
docker compose build

# Run the full test suite in Docker
docker compose run --rm test

# Open a shell inside the container
docker compose run --rm lattice bash

# Run specific tests
docker compose run --rm lattice vendor/bin/phpunit packages/workflow/tests/

# Run PHPStan
docker compose run --rm lattice vendor/bin/phpstan analyse --memory-limit=2G
```

**Docker Compose services:**

| Service | Purpose |
|---------|---------|
| `lattice` | Base framework container (PHP 8.4 CLI) |
| `test` | Runs the full test suite with `--testdox` |

To add database services for integration testing:

```yaml
# docker-compose.override.yml (create this, don't modify docker-compose.yml)
services:
  mysql:
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: lattice_test
    ports:
      - "3306:3306"

  postgres:
    image: postgres:17
    environment:
      POSTGRES_PASSWORD: secret
      POSTGRES_DB: lattice_test
    ports:
      - "5432:5432"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
```

### IDE Configuration

**PhpStorm / IntelliJ:**

1. Open the project root as a PhpStorm project
2. Set PHP interpreter to 8.4+ (Settings → PHP → CLI Interpreter)
3. Enable Composer autoload (Settings → PHP → Composer → `composer.json` in project root)
4. Configure PHPUnit (Settings → PHP → Test Frameworks → use `phpunit.xml`)
5. Configure PHPStan (Settings → PHP → Quality Tools → PHPStan → `phpstan.neon`)
6. Configure PHP CS Fixer (Settings → PHP → Quality Tools → PHP CS Fixer → `.php-cs-fixer.dist.php`)

**VS Code:**

Recommended extensions:
- `bmewburn.vscode-intelephense-client` — PHP IntelliSense
- `calebporzio.better-phpunit` — Run PHPUnit tests from the editor
- `junstyle.php-cs-fixer` — Code formatting
- `sworber.phpstan` — Static analysis

---

## Repository Structure

```
framework/
├── .github/
│   └── workflows/
│       ├── split.yml              # Monorepo split → 42 read-only repos
│       ├── tests.yml              # CI: PHPUnit, PHPStan, PHP CS Fixer
│       └── close-pull-request.yml # Template for split repos
├── packages/                      # ALL framework source code
│   ├── contracts/                 # Shared interfaces and value objects
│   ├── core/                      # Application kernel, bootstrap, lifecycle
│   ├── compiler/                  # Attribute discovery, module graph
│   ├── module/                    # Module system, DI, providers
│   ├── pipeline/                  # Guards, pipes, interceptors, filters
│   ├── http/                      # HTTP kernel
│   ├── routing/                   # Attribute-based routing
│   ├── validation/                # DTO validation (illuminate/validation)
│   ├── database/                  # DB integration (illuminate/database)
│   ├── cache/                     # Cache (illuminate/cache)
│   ├── events/                    # Events (illuminate/events)
│   ├── queue/                     # Queue (illuminate/queue)
│   ├── scheduler/                 # Task scheduling
│   ├── auth/                      # Auth kernel
│   ├── jwt/                       # JWT tokens
│   ├── authorization/             # Policies, roles, permissions
│   ├── workflow/                  # Durable execution engine
│   ├── workflow-store/            # Workflow event persistence
│   ├── observability/             # Logging, metrics, tracing
│   ├── testing/                   # Test harness
│   └── ...                        # 20+ more packages
├── starters/
│   ├── api/                       # API starter kit
│   ├── service/                   # Microservice starter
│   ├── workflow/                  # Workflow starter
│   └── grpc/                      # gRPC starter
├── examples/
│   └── crm/                       # Full CRM example
├── docs/
│   ├── adr/                       # Architecture Decision Records
│   ├── rfc/                       # Request for Comments
│   └── guides/                    # User guides
├── website/                       # VitePress documentation site
├── tasks/                         # Internal task tracking
├── composer.json                  # Root monorepo manifest
├── phpunit.xml                    # Root test configuration
├── phpstan.neon                   # Root static analysis config
├── .php-cs-fixer.dist.php         # Root code style config
├── Dockerfile                     # Development Docker image
└── docker-compose.yml             # Docker Compose services
```

**Key rule:** All framework source code lives in `packages/`. Starters, examples, docs, and website are separate concerns.

---

## Branching Strategy

| Branch | Purpose | PRs accepted? |
|--------|---------|---------------|
| `main` | Current development branch | Yes |
| `1.x` | Stable v1.x release line (created at release) | Bug fixes only |
| `feature/*` | Feature branches (your PRs) | N/A — merge into `main` |
| `fix/*` | Bug fix branches | N/A — merge into `main` or `1.x` |

**Branch naming:**

```
feature/add-redis-session-driver
feature/workflow-retry-policy
fix/router-param-matching
fix/jwt-refresh-token-rotation
docs/improve-workflow-guide
refactor/pipeline-executor-cleanup
```

**Rules:**
- Always branch from `main` for new features
- Branch from `1.x` (or latest stable) for bug fixes that need backporting
- Never push directly to `main` or `1.x`
- Delete your branch after merge

---

## Coding Standards

### PHP Version and Features

LatticePHP requires **PHP 8.4 minimum**. Use modern PHP features aggressively:

```php
<?php

declare(strict_types=1);     // REQUIRED in every file, no exceptions

// PHP 8.4 property hooks
final class UserDto
{
    public string $displayName {
        get => $this->firstName . ' ' . $this->lastName;
    }

    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
    ) {}
}

// Asymmetric visibility (PHP 8.4)
final class Config
{
    public private(set) array $items = [];
}

// Enums over string constants
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';
}

// Named arguments for constructors with many params
$config = new JwtConfig(
    algorithm: Algorithm::RS256,
    accessTokenTtl: 3600,
    refreshTokenTtl: 86400,
    issuer: 'latticephp.dev',
);
```

### Type System

Every method must declare its return type. No implicit `mixed`:

```php
// GOOD
public function findById(int $id): ?User { ... }
public function getItems(): array { ... }
public function process(Request $request): Response { ... }

// BAD — missing return type
public function findById(int $id) { ... }

// BAD — using mixed when a more specific type exists
public function getConfig(): mixed { ... }
```

Use union types and intersection types where appropriate:

```php
public function resolve(string|int $identifier): User { ... }
public function handle(Loggable&Serializable $entity): void { ... }
```

Use `@param`, `@return`, and `@var` PHPDoc annotations **only** when the type system cannot express the full type (e.g., array shapes, generic collections):

```php
/**
 * @param array<string, mixed> $config
 * @return list<Module>
 */
public function resolveModules(array $config): array { ... }
```

Do **not** add PHPDoc that merely repeats the native type signature:

```php
// BAD — redundant PHPDoc
/**
 * @param int $id
 * @return User|null
 */
public function findById(int $id): ?User { ... }
```

### Class Design

```php
// Classes are final by default
final class WorkflowRuntime { ... }

// Only open for extension when deliberately designed for it
// Document WHY it's not final
abstract class BaseGuard { ... }

// Use readonly properties for immutability
final class WorkflowEvent
{
    public function __construct(
        public readonly string $workflowId,
        public readonly EventType $type,
        public readonly array $payload,
        public readonly \DateTimeImmutable $timestamp,
    ) {}
}

// Prefer composition over inheritance
final class JwtAuthGuard implements GuardInterface
{
    public function __construct(
        private readonly JwtEncoder $encoder,
        private readonly UserRepository $users,
    ) {}
}
```

### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Classes | PascalCase | `WorkflowRuntime`, `JwtEncoder` |
| Interfaces | PascalCase + `Interface` suffix | `GuardInterface`, `PipeInterface` |
| Traits | PascalCase + descriptive name | `BelongsToWorkspace`, `HasRoles` |
| Methods | camelCase | `executeActivity()`, `findByEmail()` |
| Properties | camelCase | `$workflowId`, `$accessTokenTtl` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRY_COUNT`, `DEFAULT_TTL` |
| Enums | PascalCase (type) + PascalCase (cases) | `EventType::ActivityCompleted` |
| Config keys | dot.notation | `auth.jwt.access_ttl` |
| Route params | camelCase | `/users/{userId}/posts/{postId}` |
| DB columns | snake_case | `created_at`, `workspace_id` |
| DB tables | snake_case plural | `users`, `workflow_events` |

### Namespace Conventions

```
Lattice\                           # Root namespace
Lattice\Core\                      # packages/core/src/
Lattice\Core\Tests\                # packages/core/tests/
Lattice\Contracts\                 # packages/contracts/src/ (shared interfaces)
Lattice\Contracts\Auth\            # packages/contracts/src/Auth/
Lattice\Workflow\                  # packages/workflow/src/
Lattice\Workflow\Runtime\          # packages/workflow/src/Runtime/
Lattice\Transport\Nats\            # packages/transport-nats/src/
```

Rules:
- One class per file
- Namespace must match directory structure
- Package-internal interfaces live in `Lattice\PackageName\Contracts\`
- Shared cross-package interfaces live in `Lattice\Contracts\PackageName\`

### Attribute Design

Attributes are the **public API surface** of LatticePHP. They are the primary way developers interact with the framework:

```php
#[Module(
    imports: [DatabaseModule::class, AuthModule::class],
    controllers: [UserController::class],
    providers: [UserService::class, UserRepository::class],
    exports: [UserService::class],
)]
final class UserModule {}

#[Controller('/api/users')]
#[UseGuards(JwtAuthGuard::class)]
final class UserController
{
    #[Get()]
    public function index(#[Query] UserFilterDto $filter): array { ... }

    #[Post()]
    public function store(#[Body] CreateUserDto $dto): User { ... }

    #[Get('/{id}')]
    public function show(#[Param('id')] User $user): User { ... }
}
```

**Attribute rules:**
1. Arguments must be constant expressions (PHP limitation)
2. Use `new` expressions in attribute args for complex config: `#[CircuitBreaker(new RetryPolicy(maxAttempts: 3))]`
3. Attribute metadata is **always cached** in production — never use reflection on hot paths
4. Attributes are discovered at compile time by the Compiler package
5. Document every attribute parameter with PHPDoc on the attribute class

### Code Style (PHP CS Fixer)

We use PHP CS Fixer with the PER Coding Style + PHP 8.4 migration rules:

```bash
# Check for violations (CI runs this)
composer format:check

# Auto-fix violations
composer format
```

**Configuration** (`.php-cs-fixer.dist.php`):

| Rule | Setting | Why |
|------|---------|-----|
| `@PER-CS` | enabled | PER Coding Style (successor to PSR-12) |
| `@PHP84Migration` | enabled | Use PHP 8.4 syntax where possible |
| `strict_param` | enabled | Strict comparisons in built-in functions |
| `declare_strict_types` | enabled | `declare(strict_types=1)` in every file |
| `final_class` | enabled | Classes are final by default |
| `no_unused_imports` | enabled | Remove unused `use` statements |
| `ordered_imports` | alpha | Alphabetical import ordering |
| `single_quote` | enabled | Use `'single quotes'` for strings |
| `trailing_comma_in_multiline` | enabled | Trailing commas in multi-line arrays/params |
| `array_syntax` | short | Use `[]` not `array()` |

### Static Analysis (PHPStan)

We run PHPStan at **level max** (the strictest level):

```bash
# Run analysis
composer analyze

# With increased memory for large analysis
vendor/bin/phpstan analyse --memory-limit=2G

# Analyze a specific package
vendor/bin/phpstan analyse packages/workflow/src/
```

**All new code must pass PHPStan level max.** If you encounter existing code that doesn't pass, fix it in a separate PR or add a `@phpstan-ignore-line` with an explanation:

```php
// @phpstan-ignore-next-line — Illuminate container returns mixed, validated by GuardInterface typehint
$guard = $this->container->make($guardClass);
```

Never suppress errors without an explanation.

---

## Testing

### Test-Driven Development

LatticePHP follows TDD (Test-Driven Development). The cycle is:

1. **RED:** Write a failing test that describes the desired behavior
2. **GREEN:** Write the minimal code to make the test pass
3. **REFACTOR:** Clean up the code while keeping tests green

Every PR must include tests. PRs without tests for new behavior will not be merged.

### Test Organization

```
packages/workflow/
  tests/
    Unit/                          # Fast, isolated, no I/O
      Runtime/
        WorkflowContextTest.php
        WorkflowRuntimeTest.php
      Events/
        WorkflowEventTest.php
    Integration/                   # Slower, real dependencies
      WorkflowStressTest.php
      DatabaseEventStoreTest.php
```

| Test Type | Location | Characteristics |
|-----------|----------|-----------------|
| **Unit** | `tests/Unit/` | No I/O, no database, no network. Test one class in isolation. Use fakes/stubs for dependencies. |
| **Integration** | `tests/Integration/` | May use SQLite, real event stores, multiple classes working together. Test component boundaries. |

### Test Naming

Use descriptive test names that document the behavior:

```php
// Pattern: test_<behavior>_<condition>_<expected>

public function test_execute_activity_returns_result_on_success(): void { ... }
public function test_execute_activity_retries_on_transient_failure(): void { ... }
public function test_execute_activity_throws_after_max_retries_exhausted(): void { ... }
public function test_jwt_guard_rejects_expired_token(): void { ... }
public function test_router_matches_static_routes_before_parameterized(): void { ... }
public function test_belongs_to_workspace_auto_scopes_queries(): void { ... }
```

**Bad test names:**

```php
public function testActivity(): void { ... }        // What about it?
public function test1(): void { ... }                // Meaningless
public function test_it_works(): void { ... }        // What works?
```

### Running Tests

```bash
# Run ALL tests (2,534+ tests across 42 packages)
composer test

# Run tests for a specific package
vendor/bin/phpunit packages/workflow/tests/

# Run a specific test file
vendor/bin/phpunit packages/workflow/tests/Unit/Runtime/WorkflowContextTest.php

# Run a specific test method
vendor/bin/phpunit --filter=test_execute_activity_returns_result_on_success

# Run a specific test suite (defined in phpunit.xml)
vendor/bin/phpunit --testsuite=Workflow

# Run with coverage report
vendor/bin/phpunit --coverage-html=coverage/

# Run in Docker
docker compose run --rm test

# Run with testdox output (human-readable)
vendor/bin/phpunit --testdox
```

**Available test suites** (from `phpunit.xml`):

```
Contracts, Core, Compiler, Module, Pipeline, Http, Routing,
Validation, Auth, Jwt, Authorization, Microservices, Queue,
Workflow, Database, Observability, Testing, HttpClient, Mail,
Notifications, Cache, Events, Scheduler, DevTools
```

### Writing Good Tests

**Test one behavior per test:**

```php
// GOOD — focused on one specific behavior
public function test_compensation_runs_in_reverse_order(): void
{
    $order = [];
    $scope = new CompensationScope();
    $scope->addCompensation('A', fn() => $order[] = 'A');
    $scope->addCompensation('B', fn() => $order[] = 'B');
    $scope->addCompensation('C', fn() => $order[] = 'C');

    $scope->compensate();

    $this->assertSame(['C', 'B', 'A'], $order);
}

// BAD — testing too many things at once
public function test_compensation(): void
{
    // Tests creation, adding, compensating, error handling, all in one...
}
```

**Use fakes, not mocks, for things you own:**

```php
// GOOD — use the framework's built-in fakes
$eventStore = new InMemoryEventStore();
$runtime = new WorkflowRuntime($eventStore);

// GOOD — use the testing package's fakes
$mailer = new FakeMailer();
$notifier = new FakeNotifier();

// BAD — mocking internal interfaces
$eventStore = $this->createMock(EventStoreInterface::class);
$eventStore->method('append')->willReturn(true);
```

**Assert specific values, not just "truthy":**

```php
// GOOD
$this->assertSame('John Doe', $user->name);
$this->assertCount(3, $events);
$this->assertInstanceOf(WorkflowCompletedEvent::class, $events[2]);

// BAD
$this->assertTrue($result);
$this->assertNotEmpty($events);
```

### Testing Attributes and Metadata

When testing attribute-based behavior, test the full lifecycle:

```php
public function test_controller_route_attributes_are_discovered(): void
{
    $compiler = new AttributeCompiler();
    $routes = $compiler->discoverRoutes(UserController::class);

    $this->assertCount(3, $routes);
    $this->assertSame('GET', $routes[0]->method);
    $this->assertSame('/api/users', $routes[0]->path);
    $this->assertSame('POST', $routes[1]->method);
    $this->assertSame('/api/users', $routes[1]->path);
    $this->assertSame('GET', $routes[2]->method);
    $this->assertSame('/api/users/{id}', $routes[2]->path);
}
```

### Integration Testing

Integration tests use real (in-memory) infrastructure:

```php
public function test_workflow_completes_with_database_event_store(): void
{
    // Use real SQLite database
    $pdo = new \PDO('sqlite::memory:');
    $store = new DatabaseEventStore($pdo);
    $store->createSchema();

    $runtime = new WorkflowRuntime($store);
    $execution = $runtime->startWorkflow('order-1', OrderWorkflow::class);

    $runtime->executeWorkflow($execution);

    $events = $store->getEvents('order-1');
    $this->assertSame(EventType::WorkflowCompleted, end($events)->type);
}
```

---

## Package Development

### Creating a New Package

Before creating a new package, answer these questions:

1. **Is this genuinely a new concern?** Could it fit into an existing package?
2. **Does it have a clear, narrow responsibility?** One package = one concern.
3. **Who depends on it?** Draw the dependency arrows before writing code.
4. **Does it need to work across transports?** (HTTP, gRPC, queue, workflow)

If the answer is "yes, we need a new package," follow these steps:

```bash
# 1. Create the directory structure
mkdir -p packages/my-package/src/{Attributes,Contracts,Exceptions,Support}
mkdir -p packages/my-package/tests/{Unit,Integration}

# 2. Create the package files (see templates below)

# 3. Register in root composer.json autoload
# 4. Register in phpunit.xml test suites
# 5. Add to .github/workflows/split.yml matrix
```

### Package Directory Structure

Every package follows this structure:

```
packages/my-package/
├── src/
│   ├── Attributes/              # Public attribute definitions
│   │   └── MyAttribute.php
│   ├── Contracts/               # Package-internal interfaces
│   │   └── MyServiceInterface.php
│   ├── Exceptions/              # Package-specific exceptions
│   │   └── MyPackageException.php
│   ├── Support/                 # Internal helpers (not part of public API)
│   │   └── InternalHelper.php
│   └── MyMainClass.php
├── tests/
│   ├── Unit/
│   │   └── MyMainClassTest.php
│   └── Integration/
│       └── MyIntegrationTest.php
├── composer.json
├── phpunit.xml
└── README.md
```

### Package composer.json

```json
{
    "name": "lattice/my-package",
    "description": "Short, clear description of what this package does",
    "type": "library",
    "license": "MIT",
    "homepage": "https://latticephp.dev",
    "support": {
        "issues": "https://github.com/LatticePHP/framework/issues",
        "source": "https://github.com/LatticePHP/framework"
    },
    "require": {
        "php": "^8.4",
        "lattice/contracts": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "Lattice\\MyPackage\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Lattice\\MyPackage\\Tests\\": "tests/"
        }
    },
    "extra": {
        "branch-alias": {
            "dev-main": "1.0.x-dev"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "config": {
        "sort-packages": true
    }
}
```

### Dependency Rules

**Hard rules (never break these):**

| Rule | Explanation |
|------|-------------|
| `contracts` depends on nothing | It's the bottom of the dependency graph |
| `core` depends only on `contracts` | The kernel is minimal |
| Feature packages depend on `contracts` and optionally `core` | Never depend on other feature packages directly |
| Transport adapters depend only on transport contracts | `transport-nats` never imports `transport-rabbitmq` |
| Runtime adapters depend only on runtime contracts | `roadrunner` never imports `openswoole` |
| No circular dependencies | Ever. PHPStan will catch this. |

**Soft rules (follow unless there's a compelling reason not to):**

| Rule | Explanation |
|------|-------------|
| Prefer depending on interfaces over implementations | Use `GuardInterface` not `JwtAuthGuard` |
| Prefer depending on `contracts` over concrete packages | Use `Lattice\Contracts\Auth\` not `Lattice\Auth\` |
| Keep the dependency count low | Each dependency is a coupling point |

### Cross-Package Dependencies

```
Dependency direction (arrows point to dependencies):

starters/ ──→ feature packages ──→ core ──→ contracts
                                     │
runtime adapters ──→ runtime contracts ──┘
transport adapters ──→ transport contracts ──┘

Examples:
  packages/auth     → packages/contracts (GuardInterface)
  packages/jwt      → packages/contracts (TokenInterface)
  packages/workflow  → packages/contracts (EventStoreInterface)
  packages/routing   → packages/contracts (RouteInterface)
  packages/roadrunner → packages/contracts (RuntimeInterface)
  packages/transport-nats → packages/contracts (TransportInterface)
```

---

## Architecture Principles

### Immutable Guardrails

These five rules are non-negotiable. They define what LatticePHP is and isn't:

1. **Backend-only** — No Blade, no SSR, no frontend tooling, no React/Vue. LatticePHP is a pure backend framework. Frontends are separate applications that consume the API.

2. **Module-first** — Every feature lives inside a module. Modules declare their dependencies, providers, controllers, and exports. There is no "global" anything — if it's not in a module, it doesn't exist.

3. **Attribute + compiler** — Public metadata is expressed through PHP attributes. The compiler discovers attributes at build time and caches the results. Production code never uses reflection on the hot path.

4. **Transport-aware** — Every abstraction must be evaluated across HTTP, gRPC, message queue, and workflow transports. A guard that only works over HTTP is incomplete. A pipe that assumes JSON bodies is broken.

5. **Runtime realism** — Code must work under both PHP-FPM (stateless, process-per-request) and long-running workers (RoadRunner, OpenSwoole). Static state is forbidden. Connection pools must be managed. Memory leaks are bugs.

### Dependency Direction

Dependencies flow one way: **inward toward contracts**.

```
┌────────────────────────────────────────────────┐
│ Starters / Applications                        │
│  ┌──────────────────────────────────────────┐  │
│  │ Feature Packages (auth, workflow, etc.)   │  │
│  │  ┌────────────────────────────────────┐  │  │
│  │  │ Core (kernel, bootstrap)           │  │  │
│  │  │  ┌──────────────────────────────┐  │  │  │
│  │  │  │ Contracts (interfaces, VOs)  │  │  │  │
│  │  │  └──────────────────────────────┘  │  │  │
│  │  └────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────┘  │
└────────────────────────────────────────────────┘
```

### Contract-First Design

When adding a new capability:

1. Define the interface in `packages/contracts/src/`
2. Write tests against the interface (not the implementation)
3. Implement the interface in the appropriate package
4. Register the implementation in a module provider

This allows multiple implementations (e.g., `InMemoryEventStore` for tests, `DatabaseEventStore` for production) without changing any consuming code.

### Attribute-Driven API

The public API of LatticePHP is primarily attributes:

```php
// Module declaration
#[Module(imports: [...], controllers: [...], providers: [...], exports: [...])]

// Routing
#[Controller('/prefix')]
#[Get('/path')], #[Post('/path')], #[Put('/path')], #[Delete('/path')], #[Patch('/path')]

// Parameter binding
#[Body], #[Query], #[Param('name')], #[Header('name')], #[CurrentUser]

// Pipeline
#[UseGuards(...)], #[UsePipes(...)], #[UseInterceptors(...)], #[UseFilters(...)]

// Auth
#[Authorize], #[RequiresPermission('...')], #[RequiresRole('...')]

// Enterprise
#[BelongsToWorkspace], #[BelongsToTenant], #[Auditable]
#[Searchable], #[Filterable], #[Broadcastable]
#[RequiresFeature('...')], #[CircuitBreaker(...)]

// CQRS
#[CommandHandler], #[QueryHandler]

// Workflow
#[WorkflowMethod], #[ActivityMethod], #[SignalMethod], #[QueryMethod]
```

When designing new attributes:
- Keep the constructor simple — constant expressions only
- Provide sensible defaults for all optional parameters
- Document every parameter
- The attribute class must be in `src/Attributes/`

### Transport Awareness

Every abstraction should work across transports. Consider:

| Transport | Request source | Response destination | Auth mechanism |
|-----------|---------------|---------------------|----------------|
| HTTP | `Symfony\HttpFoundation\Request` | `Symfony\HttpFoundation\Response` | JWT header, cookie |
| gRPC | Protobuf message | Protobuf message | Metadata header |
| Queue | Job payload | Result (or none) | Queue-level auth |
| Workflow | Activity input | Activity output | Workflow-level auth |

If your code assumes `$request->headers->get('Authorization')`, it's HTTP-only. Use the `PrincipalInterface` abstraction instead.

---

## Pull Request Process

### Before You Submit

Run through this checklist:

```bash
# 1. All tests pass
composer test

# 2. Static analysis passes
composer analyze

# 3. Code style is clean
composer format:check

# 4. No accidentally committed files
git diff --cached --name-only
```

Additionally:
- [ ] Tests cover the new/changed behavior
- [ ] No breaking changes (or they're documented and intentional)
- [ ] PHPDoc is added where the type system isn't sufficient
- [ ] No `var_dump`, `dd()`, `print_r`, or debug code left in
- [ ] No hardcoded credentials, API keys, or secrets
- [ ] New classes are `final` unless deliberately designed for extension

### PR Title and Description

**Title format:** `[package] Short description of the change`

```
[workflow] Add retry policy configuration to activities
[auth] Fix JWT refresh token rotation race condition
[routing] Support optional route parameters
[contracts] Add WorkflowQueryInterface
```

**Description template:**

```markdown
## Summary

Brief explanation of what this PR does and why.

## Changes

- Added `RetryPolicy` value object with configurable max attempts, backoff, and timeout
- Modified `WorkflowContext::executeActivity()` to accept retry configuration
- Added `#[RetryPolicy]` attribute for declarative retry config on activity methods

## Testing

- 12 new unit tests for RetryPolicy value object
- 5 new integration tests for activity retry behavior
- All existing workflow tests still pass

## Breaking Changes

None.

## Related Issues

Fixes #123
```

### Review Process

1. **Automated checks:** CI runs PHPUnit, PHPStan, and PHP CS Fixer. All must pass.
2. **Code review:** At least one maintainer reviews every PR.
3. **Review criteria:**
   - Correctness: Does it do what it claims?
   - Tests: Are the tests meaningful and sufficient?
   - Architecture: Does it follow the dependency rules and design principles?
   - Performance: Are there hot-path regressions?
   - Security: Any injection, auth bypass, or data exposure risks?
   - Style: Does it match the existing codebase patterns?
4. **Iteration:** Address review feedback, push new commits (don't force-push during review).
5. **Merge:** Maintainer merges via squash-and-merge with a clean commit message.

### After Merge

After your PR is merged:

1. The `split.yml` workflow automatically pushes your changes to the relevant split repos
2. Your branch is automatically deleted
3. If your change needs documentation updates, open a follow-up PR

---

## Architecture Decision Records

For significant architectural decisions (not bug fixes or small features), write an ADR:

**Location:** `docs/adr/XXXX-title.md`

**Template:**

```markdown
# ADR-XXXX: Title

## Status

Proposed | Accepted | Deprecated | Superseded by ADR-YYYY

## Date

2026-03-22

## Context

What is the issue that we're seeing that is motivating this decision?

## Decision

What is the change that we're proposing and/or doing?

## Consequences

### Positive
- What becomes easier or possible as a result of this change?

### Negative
- What becomes more difficult as a result of this change?

### Neutral
- What other changes need to be made as a result?
```

**When to write an ADR:**
- Adding a new package
- Changing a public API contract
- Changing a dependency (adding, removing, or replacing)
- Choosing between multiple viable architectural approaches
- Changing the build, test, or release process

---

## Request for Comments (RFCs)

For **large features** that affect multiple packages or introduce new concepts, write an RFC:

**Location:** `docs/rfc/XXXX-title.md`

**Template:**

```markdown
# RFC-XXXX: Title

## Author

Your Name (@github-username)

## Status

Draft | Discussion | Accepted | Rejected | Withdrawn

## Summary

One paragraph description of the feature.

## Motivation

Why do we need this? What problem does it solve? Who benefits?

## Detailed Design

### API Design

Show the proposed attribute/class/method signatures with code examples.

### Implementation Plan

Which packages are affected? What's the implementation order?

### Migration Path

How do existing users adopt this? Are there breaking changes?

## Drawbacks

Why might we NOT want to do this?

## Alternatives

What other designs were considered and why were they rejected?

## Unresolved Questions

What aspects of the design are still TBD?
```

**RFC lifecycle:**
1. Draft → open a PR with the RFC document
2. Discussion → community feedback (minimum 1 week)
3. Decision → maintainers accept or reject
4. Implementation → accepted RFCs become tasks

---

## Release Process

LatticePHP follows [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0 → 2.0.0): Breaking API changes
- **MINOR** (1.0.0 → 1.1.0): New features, backward compatible
- **PATCH** (1.0.0 → 1.0.1): Bug fixes, backward compatible

**Release flow:**

1. Maintainer runs the release script from `main`
2. All cross-package dependencies are pinned to the release version
3. Monorepo is tagged (e.g., `v1.1.0`)
4. GitHub Actions `split.yml` pushes the tag to all 42 split repos
5. Packagist picks up the new tags automatically
6. CHANGELOG.md is updated

**Support policy:**

| Version | Status | Support |
|---------|--------|---------|
| 1.x | Active | Bug fixes and new features |
| 0.x | EOL | No longer supported |

---

## Security Vulnerabilities

**Do NOT open a public GitHub issue for security vulnerabilities.**

Email **security@latticephp.dev** with:

1. Description of the vulnerability
2. Steps to reproduce
3. Potential impact assessment
4. Suggested fix (if you have one)

**Response timeline:**
- Acknowledgement: Within 48 hours
- Initial assessment: Within 5 business days
- Fix and advisory: Coordinated with reporter

See [SECURITY.md](SECURITY.md) for full details.

---

## Community

- **GitHub Issues:** Bug reports and feature requests
- **GitHub Discussions:** Questions, ideas, and general conversation
- **Documentation:** [latticephp.dev](https://latticephp.dev)

---

## License

By contributing to LatticePHP, you agree that your contributions will be licensed under the [MIT License](LICENSE).

All contributions are subject to the [Developer Certificate of Origin (DCO)](https://developercertificate.org/). By submitting a PR, you certify that you have the right to submit the code and that it can be distributed under the MIT license.
