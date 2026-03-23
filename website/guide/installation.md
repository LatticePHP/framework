---
outline: deep
---

# Installation

## Requirements

- **PHP 8.4+** with the following extensions:
  - `ext-pdo` and `ext-pdo_sqlite` (or `ext-pdo_mysql` / `ext-pdo_pgsql`)
  - `ext-mbstring`
  - `ext-json`
  - `ext-openssl` (for JWT asymmetric keys)
- **Composer 2.x**

Verify your environment:

```bash
php -v                # Must show 8.4.x or higher
composer -V           # Must show 2.x
php -m | grep pdo     # Must show pdo and at least one driver
```

::: tip
If you're on macOS, `brew install php@8.4` gets you everything. On Ubuntu, `sudo apt install php8.4 php8.4-sqlite3 php8.4-mbstring php8.4-xml` covers the basics.
:::

## Create a New Project

The fastest way to start is with one of the four starter kits:

```bash
# API backend (recommended for most projects)
composer create-project lattice/starter-api my-app

# gRPC service
composer create-project lattice/starter-grpc my-app

# Event-driven microservice
composer create-project lattice/starter-service my-app

# Durable workflow service
composer create-project lattice/starter-workflow my-app
```

::: info
See [Starter Kits](starters.md) for a detailed comparison of what each template provides.
:::

## Configure Environment

```bash
cd my-app
cp .env.example .env
```

Open `.env` and set your application key and database:

```bash
APP_NAME=MyApp
APP_ENV=local
APP_DEBUG=true

# SQLite (zero setup, great for development)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# JWT secret (generate a random 256-bit key)
JWT_SECRET=your-random-secret-here
```

::: warning
Never commit your `.env` file to version control. It contains secrets like `JWT_SECRET` and database credentials. The `.env.example` file is committed as a template.
:::

## Create the Database

For SQLite (the default):

```bash
touch database/database.sqlite
```

For MySQL or PostgreSQL, create the database first, then update `.env`:

```bash
# MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=my_app
DB_USERNAME=postgres
DB_PASSWORD=
```

## Run Migrations

```bash
php bin/lattice migrate
```

::: tip
Run `php bin/lattice db:seed` to populate the database with sample data. The API starter includes a seeder that creates an admin user and 5 regular users.
:::

## Start the Development Server

```bash
php bin/lattice serve
```

Visit `http://localhost:8000/health` to verify:

```json
{ "status": "ok", "timestamp": "2026-03-23T10:00:00+00:00" }
```

## Docker Setup

For a containerized setup, use the included Docker Compose:

```bash
# SQLite dev server (simplest)
make up

# Full stack with PostgreSQL + Redis + workers
make up-full
```

Or build your own:

```bash
docker compose up -d
```

See [Deployment](deployment.md) for production Docker configuration.

## IDE Setup

LatticePHP uses PHP 8.4 attributes extensively. For the best experience:

- **PhpStorm** -- works out of the box with attribute support since 2021.3
- **VS Code** -- install [PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client) for attribute and type support
- **PHPStan** -- already configured in the project at level `max`

## Next Steps

- [Configuration](configuration.md) -- understand every config file and environment variable
- [Directory Structure](directory-structure.md) -- learn what each folder does
- [Your First API](getting-started.md) -- build a complete CRUD API with modules, DTOs, and tests
- [Architecture](architecture.md) -- understand the 4-layer design
