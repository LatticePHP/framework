# LatticePHP API Starter

A complete, ready-to-run API application skeleton built on the LatticePHP framework.

## Quick Start

### 1. Create a New Project

```bash
composer create-project lattice/starter-api myapp
cd myapp
```

### 2. Configure Environment

```bash
cp .env.example .env
# Edit .env with your settings (SQLite works out of the box)
```

### 3. Run Migrations

```bash
php bin/lattice migrate
```

### 4. Seed the Database

```bash
php bin/lattice db:seed
```

### 5. Start the Development Server

```bash
php bin/lattice serve
# API available at http://localhost:8080
```

### 6. Run Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

## Project Structure

```
app/
  Models/           Eloquent models
  Modules/          Application modules (NestJS-style)
    App/            Root application module
  Providers/        Service providers
  Http/             HTTP controllers
  Dto/              Data Transfer Objects
bin/
  lattice           CLI entry point
bootstrap/
  app.php           Application bootstrap
config/             Configuration files
database/
  migrations/       Database migrations
  seeders/          Database seeders
  factories/        Model factories
public/
  index.php         HTTP entry point
routes/             Route files (attribute routing preferred)
storage/            Application storage (logs, cache, etc.)
tests/              Test files
```

## Key Concepts

### Attribute-Based Routing

Controllers use PHP attributes for route definitions:

```php
#[Controller('/users')]
final class UserController
{
    #[Get('/')]
    public function index(): array { ... }

    #[Post('/')]
    public function create(#[Body] CreateUserDto $dto): array { ... }
}
```

### Module Architecture

The application is organized into modules (NestJS-style):

```php
#[Module(
    imports: [AuthModule::class],
)]
final class AppModule {}
```

### Built-in Endpoints

- `GET /health` - Health check endpoint
- `GET /users` - List users (example)
- `POST /users` - Create user (example)

## Configuration

All configuration lives in the `config/` directory:

- `app.php` - Application name, environment, debug, URL, timezone
- `auth.php` - Authentication guards, JWT config, password hashing
- `cache.php` - Cache stores (array, file, redis)
- `cors.php` - CORS allowed origins, methods, headers
- `database.php` - Database connections (sqlite, mysql, pgsql)
- `filesystems.php` - Filesystem disks (local, public, s3)
- `logging.php` - Log channels (stack, daily, stderr)
- `mail.php` - Mail drivers (smtp, log, array)
- `observability.php` - Health checks, tracing, metrics, audit
- `queue.php` - Queue connections (sync, database, redis)
- `tenancy.php` - Multi-tenancy configuration
- `workspace.php` - Workspace configuration

## Environment Variables

Copy `.env.example` for a full list of all available environment variables with documentation.

## License

MIT
