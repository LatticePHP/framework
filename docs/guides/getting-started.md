# Getting Started with LatticePHP

This guide walks you through creating your first LatticePHP API, using real patterns from the working CRM example and starter kit.

## Prerequisites

- **PHP 8.4+** with extensions: `ext-pdo`, `ext-pdo_sqlite`, `ext-mbstring`, `ext-json`
- **Composer 2.x**
- **SQLite** (default) or MySQL/PostgreSQL

Verify your environment:

```bash
php -v          # Must show 8.4.x or higher
composer -V     # Must show 2.x
php -m | grep pdo_sqlite
```

## Installation

```bash
composer create-project lattice/starter-api myapp
cd myapp
```

## Project Structure

```
myapp/
  app/
    AppModule.php              # Root module (registers controllers)
    Http/
      HealthController.php     # Health check endpoint
      UserController.php       # Example CRUD controller
    Dto/
      CreateUserDto.php        # Validated request DTO
      UserResource.php         # Response serializer
    Models/
      User.php                 # Eloquent model
    Modules/
      App/
        AppModule.php          # Module with imports (e.g., AuthModule)
    Providers/
      AppServiceProvider.php   # Service provider for bindings
  bootstrap/
    app.php                    # Application bootstrap
  bin/
    lattice                    # CLI entry point
  config/
    app.php                    # App config
    database.php               # Database connections
    cors.php                   # CORS settings
    auth.php                   # Auth config
  database/
    migrations/                # Migration files
    seeders/                   # Seeder classes
    factories/                 # Model factories
  public/
    index.php                  # HTTP entry point
  storage/                     # Logs, cache, temp files
  tests/                       # PHPUnit tests
```

## How Bootstrap Works

The entry point is `public/index.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Lattice\Core\Http\RequestFactory;
use Lattice\Core\Http\ResponseEmitter;

$app = require __DIR__ . '/../bootstrap/app.php';
$request = RequestFactory::fromGlobals();
$response = $app->handleRequest($request);
ResponseEmitter::emit($response);
```

The bootstrap file (`bootstrap/app.php`) configures and creates the application:

```php
<?php
declare(strict_types=1);

use Lattice\Core\Application;
use App\Modules\App\AppModule;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    \Lattice\Core\Environment\EnvLoader::loadFile($basePath . '/.env');
}

if (class_exists(\Illuminate\Database\Capsule\Manager::class)) {
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $dbConfig = require $basePath . '/config/database.php';
    $default = $dbConfig['default'] ?? 'sqlite';
    $capsule->addConnection($dbConfig['connections'][$default] ?? [
        'driver' => 'sqlite',
        'database' => $basePath . '/database/database.sqlite',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
}

return Application::configure(basePath: $basePath)
    ->withModules([AppModule::class])
    ->withHttp()
    ->create();
```

The builder chain: `Application::configure()` returns an `ApplicationBuilder`. Call `withModules()` to register your root module, `withHttp()` to enable the HTTP transport, and `create()` to produce the `Application` instance.

## First Run

```bash
cd myapp
php bin/lattice serve
```

Visit `http://localhost:8000/health` to see:

```json
{"status": "ok", "timestamp": "2026-03-22T10:00:00+00:00"}
```

That response comes from the starter's `HealthController`:

```php
<?php
declare(strict_types=1);

namespace App\Http;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/health')]
final class HealthController
{
    #[Get('/')]
    public function check(): array
    {
        return [
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];
    }
}
```

Returning an array from a controller method automatically serializes it to JSON with a 200 status.

## Create Your First Module

Modules are the organizational unit in LatticePHP. Every feature lives in a module. Here is how the CRM structures its Contacts feature:

```
app/Modules/Contacts/
  ContactsModule.php
  ContactController.php
  ContactService.php
  ContactResource.php
  Dto/
    CreateContactDto.php
    UpdateContactDto.php
```

### Define the Module

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

### Register It in Your Root Module

```php
<?php
declare(strict_types=1);

namespace App;

use App\Modules\Contacts\ContactsModule;
use Lattice\Module\Attribute\Module;

#[Module(
    imports: [ContactsModule::class],
)]
final class AppModule {}
```

The `imports` array triggers recursive module discovery. When the application boots, it walks the import tree, registers all providers, and collects all controllers.

## Create a Controller

```php
<?php
declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Models\Contact;
use App\Modules\Contacts\Dto\CreateContactDto;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;

#[Controller('/api/contacts')]
final class ContactController
{
    #[Get('/')]
    public function index(): array
    {
        return ContactResource::collection(Contact::all());
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): array
    {
        $contact = Contact::findOrFail($id);
        return ContactResource::make($contact)->toArray();
    }

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto): Response
    {
        $contact = Contact::create([
            'first_name' => $dto->first_name,
            'last_name' => $dto->last_name,
            'email' => $dto->email,
        ]);

        return ResponseFactory::created(
            ['data' => ContactResource::make($contact)->toArray()],
        );
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        Contact::findOrFail($id)->delete();
        return ResponseFactory::noContent();
    }
}
```

> **Tip:** For standard CRUD resources, extend `CrudController` and `CrudService` to get index/show/destroy/create/update with zero boilerplate. See the [HTTP API guide](./http-api.md) and [Database guide](./database.md) for details.

## Create a Model

Models extend `Lattice\Database\Model` (which extends Eloquent's base Model):

```php
<?php
declare(strict_types=1);

namespace App\Models;

use Lattice\Database\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Contact extends Model
{
    use SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'status'];

    protected $casts = ['deleted_at' => 'datetime'];
}
```

## Create a Migration

Place migration files in `database/migrations/`:

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('status')->default('lead');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
```

## Run Migrations and Seeds

```bash
php bin/lattice migrate
php bin/lattice db:seed
```

## Run Tests

```bash
php bin/lattice test
# or directly:
./vendor/bin/phpunit
```

## Next Steps

- [Architecture](architecture.md) -- understand the 4-layer design and request lifecycle
- [Modules](modules.md) -- module system deep dive with imports, exports, providers
- [HTTP API](http-api.md) -- controllers, DTOs, resources, pagination, error handling
- [Auth](auth.md) -- JWT, guards, workspace isolation
- [Database](database.md) -- Eloquent, migrations, filtering, search
