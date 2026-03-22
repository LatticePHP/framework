---
outline: deep
---

# Package Authoring

LatticePHP packages are Composer packages that expose one or more modules. Consumers import the module and get a fully wired feature -- providers, controllers, migrations, and configuration.

## Package Structure

```
packages/my-feature/
  src/
    Attributes/           # Custom attributes
    Contracts/            # Package-internal interfaces
    Exceptions/           # Exception classes
    Support/              # Internal helpers
    MyFeatureController.php
    MyFeatureService.php
    MyFeatureModule.php
  tests/
    Unit/
    Integration/
  composer.json
  phpunit.xml
  README.md
```

## Step 1: Create composer.json

```json
{
    "name": "lattice/my-feature",
    "description": "My feature package for LatticePHP",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "lattice/contracts": "^1.0",
        "lattice/module": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Lattice\\MyFeature\\": "src/"
        }
    },
    "extra": {
        "lattice": {
            "module": "Lattice\\MyFeature\\MyFeatureModule"
        }
    }
}
```

The `extra.lattice.module` key tells the compiler which class is the package's entry module.

## Step 2: Define the Module

```php
<?php

declare(strict_types=1);

namespace Lattice\MyFeature;

use Lattice\Module\Attribute\Module;

#[Module(
    imports: [],
    providers: [
        MyFeatureService::class,
        MyFeatureRepository::class,
    ],
    controllers: [
        MyFeatureController::class,
    ],
    exports: [
        MyFeatureService::class,
    ],
)]
final class MyFeatureModule {}
```

- **imports** -- other modules this module depends on
- **providers** -- classes to register in the DI container
- **controllers** -- controller classes with route attributes
- **exports** -- services available to modules that import this one

## Step 3: Create the Controller

```php
<?php

declare(strict_types=1);

namespace Lattice\MyFeature;

use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Param;

#[Controller('/api/features')]
final class MyFeatureController
{
    public function __construct(
        private readonly MyFeatureService $service,
    ) {}

    #[Get('/')]
    public function index(): Response
    {
        return ResponseFactory::json(['data' => $this->service->listAll()]);
    }

    #[Post('/')]
    public function create(#[Body] CreateFeatureDto $dto): Response
    {
        $feature = $this->service->create($dto);
        return ResponseFactory::created(['data' => $feature]);
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $feature = $this->service->findOrFail($id);
        return ResponseFactory::json(['data' => $feature]);
    }
}
```

## Step 4: Add Models and Migrations

Models extend `Lattice\Database\Model`:

```php
<?php

declare(strict_types=1);

namespace Lattice\MyFeature;

use Lattice\Database\Model;

final class Feature extends Model
{
    protected $fillable = ['name', 'description', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
```

Migrations use Illuminate's Schema Builder:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('features');
    }
};
```

## Step 5: Write Tests

Follow TDD. Write tests first, then implementation.

```php
<?php

declare(strict_types=1);

namespace Lattice\MyFeature\Tests\Unit;

use Lattice\MyFeature\MyFeatureService;
use PHPUnit\Framework\TestCase;

final class MyFeatureServiceTest extends TestCase
{
    public function test_list_all_returns_array(): void
    {
        $repo = new InMemoryFeatureRepository();
        $service = new MyFeatureService($repo);

        $result = $service->listAll();

        $this->assertIsArray($result);
    }
}
```

Use in-memory fakes for repositories in unit tests, not mocks. This follows the LatticePHP convention: "no mocking of things you own."

## Dynamic Module Factories

For packages that need runtime configuration, use `DynamicModuleDefinition`:

```php
<?php

declare(strict_types=1);

namespace Lattice\MyFeature;

use Lattice\Module\DynamicModuleDefinition;

final class MyFeatureModule
{
    public static function register(array $config = []): DynamicModuleDefinition
    {
        return DynamicModuleDefinition::create(
            providers: [
                MyFeatureService::class,
            ],
            controllers: [
                MyFeatureController::class,
            ],
            exports: [
                MyFeatureService::class,
            ],
            factoryBindings: [
                MyFeatureConfig::class => fn () => new MyFeatureConfig(
                    apiKey: $config['api_key'] ?? '',
                    timeout: $config['timeout'] ?? 30,
                ),
            ],
        );
    }
}
```

Consumers use it:

```php
#[Module(
    imports: [
        MyFeatureModule::register(['api_key' => env('FEATURE_KEY'), 'timeout' => 10]),
    ],
)]
final class AppModule {}
```

## Conventions

1. **Final classes by default.** Only leave a class non-final if extension is a deliberate design choice.
2. **Strict types.** Every file starts with `declare(strict_types=1);`.
3. **Readonly properties** for immutable state.
4. **Return types** on every method.
5. **Namespace pattern:** `Lattice\PackageName\` for source, `Lattice\PackageName\Tests\` for tests.
6. **Contracts in `packages/contracts/`** -- public interfaces go in the shared contracts package.

## Publishing to Packagist

1. Push to a public Git repository
2. Register on [packagist.org](https://packagist.org)
3. Tag a release: `git tag v1.0.0 && git push --tags`
4. Consumers install: `composer require lattice/my-feature`
