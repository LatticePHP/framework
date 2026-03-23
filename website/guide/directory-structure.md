---
outline: deep
---

# Directory Structure

When you create a new project with `composer create-project lattice/starter-api`, you get the following structure:

```
my-app/
  app/                    # Your application code
  bootstrap/              # Application bootstrap
  bin/                    # CLI entry point
  config/                 # Configuration files
  database/               # Migrations, factories, seeders
  public/                 # HTTP entry point
  storage/                # Logs, cache, temp files
  tests/                  # PHPUnit tests
  vendor/                 # Composer dependencies
  .env                    # Environment variables (not committed)
  .env.example            # Environment template (committed)
  composer.json           # Dependencies and autoloading
  phpunit.xml             # Test configuration
```

## The `app/` Directory

This is where your application code lives. LatticePHP encourages a modular structure:

### Flat Layout (small APIs)

```
app/
  Modules/
    App/
      AppModule.php           # Root module
  Http/
    HealthController.php      # Controllers at the top level
    UserController.php
  Dto/
    CreateUserDto.php         # Request DTOs
  Models/
    User.php                  # Eloquent models
  Providers/
    AppServiceProvider.php    # Service providers
```

### Modular Layout (larger applications)

As your application grows, organize by feature modules:

```
app/
  AppModule.php               # Root module (imports feature modules)
  Models/                     # Shared Eloquent models
    User.php
    Contact.php
    Company.php
  Modules/
    Auth/
      AuthModule.php
    Contacts/
      ContactsModule.php      # Module definition
      ContactController.php   # Routes for this feature
      ContactService.php      # Business logic
      ContactResource.php     # JSON response shape
      Dto/
        CreateContactDto.php  # Validated input
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

Each feature module is self-contained with its own controller, service, DTOs, and resource. The `#[Module]` attribute declares dependencies explicitly. See [Modules](modules.md) for the full guide.

> Services can extend `Lattice\Database\Crud\CrudService` for instant CRUD operations.
> Controllers can extend `Lattice\Http\Crud\CrudController` for instant CRUD endpoints.

::: tip
Place models in a shared `app/Models/` directory when multiple modules reference them (e.g., `User`, `Contact`). Keep module-specific code inside its module folder.
:::

## The `bootstrap/` Directory

Contains a single file, `app.php`, which configures and creates the application:

```php
return Application::configure(basePath: $basePath)
    ->withModules([AppModule::class])
    ->withHttp()
    ->create();
```

This is the only place you configure the application bootstrap. Modules, transports (HTTP, gRPC), and observability are enabled here.

## The `bin/` Directory

Contains the `lattice` CLI entry point. Run commands with:

```bash
php bin/lattice <command>
```

See [CLI Commands](cli.md) for the full list of 47 available commands.

## The `config/` Directory

PHP files that return configuration arrays. Each file controls a subsystem:

| File | Controls |
|---|---|
| `app.php` | Application name, environment, debug mode, timezone |
| `auth.php` | JWT settings, guard drivers, password hashing |
| `database.php` | Database connections (SQLite, MySQL, PostgreSQL) |
| `cors.php` | Cross-origin resource sharing |
| `cache.php` | Cache drivers (file, Redis, array) |
| `queue.php` | Queue connections (sync, database, Redis) |
| `logging.php` | Log channels (daily file, stderr, stack) |
| `mail.php` | Mail transport (SMTP, log) |
| `filesystems.php` | File storage drivers (local, S3) |
| `observability.php` | Tracing, metrics, audit logging |
| `workspace.php` | Workspace isolation settings |
| `tenancy.php` | Multi-tenancy configuration |

See [Configuration](configuration.md) for the full reference.

## The `database/` Directory

```
database/
  migrations/             # Schema change files (timestamped)
  factories/              # Model factories for testing
  seeders/                # Database seeders for sample data
  database.sqlite         # SQLite database file (if using SQLite)
```

Migrations use Illuminate's schema builder. Run them with `php bin/lattice migrate`.

## The `public/` Directory

Contains `index.php`, the HTTP entry point for PHP-FPM. Your web server (Nginx, Apache) should point its document root here.

```php
$app = require __DIR__ . '/../bootstrap/app.php';
$request = RequestFactory::fromGlobals();
$response = $app->handleRequest($request);
ResponseEmitter::emit($response);
```

::: info
When using RoadRunner, the entry point is a worker file instead of `public/index.php`. See [Runtime](runtime.md).
:::

## The `storage/` Directory

```
storage/
  logs/                   # Application log files
  cache/                  # File-based cache
  framework/              # Framework temp files
```

This directory must be writable by the web server process.

## The `tests/` Directory

```
tests/
  TestCase.php            # Base test class
  Feature/                # Feature/integration tests
    ExampleTest.php
  Unit/                   # Unit tests (optional)
```

Tests extend `Lattice\Testing\TestCase` which bootstraps a real application instance. See [Testing](testing.md).

## Next Steps

- [Your First API](getting-started.md) -- build a complete module with controller, DTO, and model
- [Modules](modules.md) -- how the module system organizes your code
- [CLI Commands](cli.md) -- scaffolding with `make:module`, `make:controller`, `make:dto`
