---
outline: deep
---

# LatticePHP Architecture

## The 4-Layer Architecture

LatticePHP is organized into four layers. Each layer depends only on layers below it.

```
Layer 4: Feature Modules       (App\Modules\Contacts, App\Modules\Deals, ...)
Layer 3: Framework Packages    (lattice/http, lattice/routing, lattice/module, ...)
Layer 2: Illuminate Components (illuminate/container, illuminate/database, ...)
Layer 1: PHP Runtime           (PHP 8.4+, ext-pdo, ext-mbstring)
```

**Layer 1 -- PHP Runtime.** PHP 8.4+ with attributes, readonly properties, property hooks, enums. No polyfills.

**Layer 2 -- Illuminate Components.** Selected Laravel packages used standalone (not the full framework):
- `illuminate/container` -- dependency injection container
- `illuminate/database` -- Eloquent ORM, query builder, migrations
- `illuminate/queue` -- job queue foundation
- `illuminate/validation` -- validation engine
- `illuminate/cache` -- cache layer (Redis, file, array)
- `illuminate/events` -- event dispatcher
- `illuminate/console` -- CLI commands (Symfony Console underneath)
- `illuminate/filesystem` -- file storage via Flysystem

**Layer 3 -- Framework Packages.** The `lattice/*` packages that provide the module system, HTTP kernel, routing, pipeline, and more. Each is a separate Composer package in the `packages/` directory.

**Layer 4 -- Feature Modules.** Your application code. Each feature is a module class annotated with `#[Module]` containing controllers, services, and providers.

## How Boot Actually Works

The boot sequence starts in `bootstrap/app.php` and follows this path:

```
Application::configure(basePath: $basePath)   // Creates ApplicationBuilder
    ->withModules([AppModule::class])          // Registers root module classes
    ->withHttp()                               // Enables HTTP transport
    ->create()                                 // Returns Application instance
```

The `create()` method constructs `Application` with:
- A `ContainerInterface` (defaults to `IlluminateContainer` backed by `illuminate/container`)
- A `ConfigRepository` for configuration
- A `LifecycleManager` for boot/shutdown hooks
- The list of root module classes

When `handleRequest()` is called (or `boot()` is called explicitly), the application:

1. **Discovers modules** -- reads the `#[Module]` attribute from each root module class, follows `imports` recursively to build the full module graph
2. **Registers providers** -- calls `register()` on every provider class listed in module `providers` arrays
3. **Boots providers** -- calls `boot()` on every registered provider
4. **Collects controllers** -- gathers all controller classes from module `controllers` arrays
5. **Caches the router** -- builds route definitions once, reuses on subsequent requests (important for long-running workers)

This is proven in the integration test suite:

```php
// From FrameworkIntegrationTest
$this->app = new Application(
    basePath: $this->basePath,
    modules: [TestModule::class],
);

$this->app->boot();
$moduleDefinitions = $this->app->getModuleDefinitions();
// TestModule is discovered with its controllers and providers
```

## The Request Lifecycle

Every HTTP request follows this exact path, proven by the integration tests:

```
public/index.php
    |
    v
RequestFactory::fromGlobals()          -- creates Lattice\Http\Request from PHP globals
    |
    v
Application::handleRequest($request)   -- main entry point
    |
    v
boot() if not already booted           -- module discovery, provider registration
    |
    v
Router::match($method, $path)          -- finds matching RouteDefinition
    |                                      returns 404 Response if no match
    v
Pipeline execution                     -- runs guards (UseGuards), interceptors
    |                                      returns 403 if guard denies
    v
ParameterResolver                      -- resolves controller method params:
    |                                      #[Body] -> DTO deserialization + validation
    |                                      #[Param] -> path parameter (auto-coerced)
    |                                      #[Query] -> query string parameter
    |                                      #[CurrentUser] -> Principal from guard context
    |                                      Request -> auto-injected (no attribute needed)
    v
Controller method execution            -- your code runs here
    |
    v
Response serialization                 -- array return -> JSON 200
    |                                      Response object -> passed through
    |                                      void return -> 204 No Content
    v
ExceptionHandler / ExceptionRenderer   -- catches exceptions:
    |                                      ModelNotFoundException -> 404
    |                                      ValidationException -> 422
    |                                      ForbiddenException -> 403
    |                                      RuntimeException -> 500
    |                                      All errors use RFC 9457 Problem Details
    v
ResponseEmitter::emit($response)       -- sends headers + body to client
```

This lifecycle is tested end-to-end in `FrameworkIntegrationTest`:

```php
// Full CRUD lifecycle proven working
$createResponse = $this->handleRequest('POST', '/api/test/contacts', [
    'name' => 'CRUD Test',
    'email' => 'crud@test.com',
    'status' => 'active',
]);
// 201 Created

$readResponse = $this->handleRequest('GET', '/api/test/contacts/' . $id);
// 200 OK with resource-shaped body

$deleteResponse = $this->handleRequest('DELETE', '/api/test/contacts/' . $id);
// 204 No Content

$verifyResponse = $this->handleRequest('GET', '/api/test/contacts/' . $id);
// 404 Not Found
```

## The Module System

Modules use the `#[Module]` attribute to declare their dependencies and contents:

```php
#[Module(
    imports: [ContactsModule::class, CompaniesModule::class],  // other modules
    providers: [ContactService::class],                         // DI bindings
    controllers: [ContactController::class],                    // HTTP controllers
    exports: [ContactService::class],                           // shared with importing modules
)]
final class AppModule {}
```

The CRM application demonstrates this with a root module importing seven feature modules:

```php
#[Module(
    imports: [
        AuthModule::class,
        ContactsModule::class,
        CompaniesModule::class,
        DealsModule::class,
        ActivitiesModule::class,
        NotesModule::class,
        DashboardModule::class,
    ],
)]
final class AppModule {}
```

Each feature module is self-contained. `ContactsModule` declares its own service, controller, and exports:

```php
#[Module(
    providers: [ContactService::class],
    controllers: [ContactController::class],
    exports: [ContactService::class],
)]
final class ContactsModule {}
```

See the [Modules guide](modules.md) for the full deep dive.

## Illuminate Underneath

LatticePHP uses Illuminate components as building blocks, not as a framework. Key differences:

| Concern | Laravel | LatticePHP |
|---------|---------|------------|
| Entry point | `Kernel::handle()` | `Application::handleRequest()` |
| Routing | `routes/web.php` files | `#[Controller]` + `#[Get]` attributes on classes |
| DI | Service container + facades | `IlluminateContainer` + constructor injection |
| Modules | None (service providers only) | `#[Module]` with imports/exports/controllers |
| Config | `config/*.php` + `app()` helper | `ConfigRepository` + env loading |
| HTTP | Symfony HttpFoundation | `Lattice\Http\Request` / `Response` value objects |

Eloquent works exactly as you know it -- `Model`, `SoftDeletes`, `HasFactory`, relationships, query builder, migrations. The difference is how it is booted (via `Capsule::Manager` in `bootstrap/app.php` instead of Laravel's service container).

## What Makes LatticePHP Different

1. **Module-first architecture** -- every feature is a module with explicit imports/exports, not a loose collection of service providers
2. **Attribute-driven API** -- routes, guards, validation, and parameter binding are all declared via PHP 8.4 attributes
3. **Compiler pipeline** -- attribute metadata is discovered once at boot, cached, and never reflected on hot paths
4. **Transport-aware** -- the same module system works across HTTP, gRPC, message queues, and workflows
5. **Runtime-flexible** -- works under PHP-FPM and long-running workers (RoadRunner, OpenSwoole) with cached router state
6. **Native durable workflows** -- Temporal-class workflow orchestration without external infrastructure (uses your existing DB + queue)
7. **Backend-only** -- no Blade, no SSR, no frontend tooling. API-first by design.

## Key Classes Reference

| Class | Package | Purpose |
|-------|---------|---------|
| `Application` | `lattice/core` | Application kernel, boot, request handling |
| `ApplicationBuilder` | `lattice/core` | Fluent builder for Application configuration |
| `RequestFactory` | `lattice/core` | Creates Request from PHP globals |
| `ResponseEmitter` | `lattice/core` | Sends Response to client |
| `Router` | `lattice/routing` | Route matching and definition storage |
| `HttpKernel` | `lattice/http` | HTTP request processing pipeline |
| `ParameterResolver` | `lattice/http` | Resolves controller method parameters |
| `ResponseFactory` | `lattice/http` | Static factory for common responses |
| `Resource` | `lattice/http` | Base class for API resources |
| `ModuleRegistry` | `lattice/module` | Stores discovered module definitions |
| `ModuleBootstrapper` | `lattice/module` | Runs provider register/boot lifecycle |
| `ServiceProvider` | `lattice/core` | Base class for service providers |
| `Model` | `lattice/database` | Base Eloquent model class |
| `Principal` | `lattice/auth` | Authenticated user identity |
