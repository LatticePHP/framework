---
outline: deep
---

# Modules

## What Is a Module

A module is a self-contained unit of functionality declared with the `#[Module]` attribute. It groups providers (services), controllers, and exports into a cohesive boundary with explicit dependencies.

Here is the CRM's root module -- the single entry point that imports all feature modules:

```php
<?php
declare(strict_types=1);

namespace App;

use App\Modules\Activities\ActivitiesModule;
use App\Modules\Auth\AuthModule;
use App\Modules\Companies\CompaniesModule;
use App\Modules\Contacts\ContactsModule;
use App\Modules\Dashboard\DashboardModule;
use App\Modules\Deals\DealsModule;
use App\Modules\Notes\NotesModule;
use Lattice\Module\Attribute\Module;

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

This module is registered in `bootstrap/app.php`:

```php
return Application::configure(basePath: $basePath)
    ->withModules([AppModule::class])
    ->withHttp()
    ->create();
```

## The #[Module] Attribute

The attribute accepts four arrays:

```php
#[Module(
    imports: [],       // Other module classes this module depends on
    providers: [],     // Service classes to register in the DI container
    controllers: [],   // Controller classes that handle HTTP requests
    exports: [],       // Services to make available to importing modules
)]
```

### A Feature Module (from the CRM)

```php
<?php
declare(strict_types=1);

namespace App\Modules\Contacts;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [ContactService::class],
    controllers: [ContactController::class],
    exports: [ContactService::class],
)]
final class ContactsModule {}
```

This declares:
- `ContactService` is registered as a provider (available for injection within this module)
- `ContactController` handles HTTP routes
- `ContactService` is exported (other modules that import `ContactsModule` can inject it)

### A Minimal Module (from integration tests)

```php
#[Module(
    controllers: [TestController::class],
)]
final class TestModule {}
```

A module with only controllers and no providers or imports works fine. The framework discovers routes from the controller and handles DI through the container.

## Two #[Module] Attribute Classes

LatticePHP has two `Module` attribute classes:

| Class | Namespace | Extra Feature |
|-------|-----------|---------------|
| `Lattice\Module\Attribute\Module` | `lattice/module` | Standard module definition |
| `Lattice\Compiler\Attributes\Module` | `lattice/compiler` | Adds `global: bool` parameter |

**Which to use:** Use `Lattice\Module\Attribute\Module` for application code. This is what the CRM uses for all its feature modules. The compiler variant adds a `global` flag for framework-level modules that should be available everywhere without explicit imports.

The starter kit's root `AppModule` uses the compiler variant:

```php
use Lattice\Compiler\Attributes\Module;

#[Module(
    imports: [],
    providers: [],
    controllers: [HealthController::class, UserController::class],
    exports: [],
)]
final class AppModule {}
```

Both attributes have identical `imports`, `providers`, `controllers`, and `exports` parameters. The compiler variant just adds `global: bool` (defaults to `false`).

## Module Lifecycle

When the application boots, modules go through a defined lifecycle:

### 1. Discovery

The application reads the `#[Module]` attribute from each root module class passed to `withModules()`. It then recursively follows the `imports` array to discover the full module graph.

```
AppModule
  -> AuthModule
  -> ContactsModule
  -> CompaniesModule
  -> DealsModule
  -> ActivitiesModule
  -> NotesModule
  -> DashboardModule
```

### 2. Provider Registration

For each discovered module, the framework calls `register()` on every class in the `providers` array. This is where services bind themselves into the container:

```php
$this->app->getContainer()->singleton(TestService::class, TestService::class);
```

### 3. Provider Boot

After all providers are registered, the framework calls `boot()` on each. This is where providers can use other services that were registered in step 2.

### 4. Controller Collection

All `controllers` arrays from all modules are merged. The `RouteDiscoverer` reads `#[Controller]`, `#[Get]`, `#[Post]`, etc. attributes to build route definitions.

This lifecycle is verified in integration tests:

```php
$this->app->boot();

$moduleDefinitions = $this->app->getModuleDefinitions();
$this->assertNotEmpty($moduleDefinitions);
$this->assertArrayHasKey(TestModule::class, $moduleDefinitions);

$controllers = $this->app->getControllers();
$this->assertContains(TestController::class, $controllers);
```

## Recursive Module Discovery

Imports form a tree. The framework walks it depth-first:

```php
// Root module imports ContactsModule and CompaniesModule
#[Module(imports: [ContactsModule::class, CompaniesModule::class])]
final class AppModule {}

// ContactsModule has no imports
#[Module(providers: [ContactService::class], controllers: [ContactController::class])]
final class ContactsModule {}

// CompaniesModule has no imports
#[Module(providers: [CompanyService::class], controllers: [CompanyController::class])]
final class CompaniesModule {}
```

The discovery order is: `AppModule` -> `ContactsModule` -> `CompaniesModule`. Each module is discovered exactly once (duplicates are deduplicated).

## Cross-Module Exports

The `exports` array controls what a module shares with its importers.

```php
#[Module(
    providers: [ContactService::class, ContactRepository::class],
    controllers: [ContactController::class],
    exports: [ContactService::class],  // Only ContactService is shared
)]
final class ContactsModule {}
```

`ContactRepository` stays private to `ContactsModule`. Only `ContactService` is available for injection in modules that import `ContactsModule`.

In the CRM, `ContactsModule` exports its service so that other modules (like DealsModule) can inject `ContactService` to look up contacts when creating deals.

## ServiceProvider Integration

For complex setup logic, use a `ServiceProvider` class in the `providers` array:

```php
<?php
declare(strict_types=1);

namespace App\Providers;

use Lattice\Core\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interfaces to implementations
        // $this->app->getContainer()->singleton(FooInterface::class, FooImpl::class);
    }

    public function boot(): void
    {
        // Run after all providers are registered
        // Access other services safely here
    }
}
```

Register it in a module:

```php
#[Module(
    imports: [AuthModule::class],
    providers: [AppServiceProvider::class],
)]
final class AppModule {}
```

## Dynamic Modules

Some framework modules accept configuration. For example, setting up a database connection:

```php
use Lattice\Core\Application;

$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => $basePath . '/database/database.sqlite',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
```

This pattern is used in both the starter kit and the CRM's `bootstrap/app.php`. The database is configured before the Application is created, then Eloquent is available to all modules.

## Module Organization Patterns

### Flat (starter kit)

For small APIs, put controllers and DTOs at the app level:

```
app/
  AppModule.php
  Http/
    HealthController.php
    UserController.php
  Dto/
    CreateUserDto.php
  Models/
    User.php
```

### Modular (CRM)

For larger applications, each feature gets its own module directory:

```
app/
  AppModule.php
  Models/
    Contact.php
    Company.php
    Deal.php
  Modules/
    Contacts/
      ContactsModule.php
      ContactController.php
      ContactService.php
      ContactResource.php
      Dto/
        CreateContactDto.php
        UpdateContactDto.php
    Companies/
      CompaniesModule.php
      CompanyController.php
      CompanyService.php
    Deals/
      DealsModule.php
      DealController.php
      DealService.php
```

The CRM places models in a shared `app/Models/` directory (since multiple modules reference them) and keeps module-specific code (controllers, services, DTOs, resources) inside the module folder.

## Constructor Injection

Controllers receive their dependencies through constructor injection. The DI container resolves them automatically:

```php
#[Controller('/api/contacts')]
final class ContactController
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
    {
        $contact = $this->service->create($dto, $user);
        return ResponseFactory::created(
            ['data' => ContactResource::make($contact)->toArray()],
        );
    }
}
```

This works because `ContactService` is listed in the module's `providers` array. The container resolves it and passes it to the controller constructor.

Proven in integration tests:

```php
// Constructor injection test
$response = $this->handleRequest('GET', '/api/test/greet/World');
$this->assertSame(200, $response->statusCode);
$this->assertSame('Hello, World!', $response->body['greeting']);
```
