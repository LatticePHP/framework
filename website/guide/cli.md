---
outline: deep
---

# CLI Commands

LatticePHP provides 47 CLI commands for development, database management, code generation, and production operations. All commands are run through the `lattice` binary:

```bash
php bin/lattice <command> [arguments] [options]
```

## Code Generation

Scaffold new classes with the `make:*` commands. Each generates a properly structured file following LatticePHP conventions (`declare(strict_types=1)`, `final` classes, correct namespace).

```bash
# Create a new module
php bin/lattice make:module Contacts
# -> app/Modules/Contacts/ContactsModule.php

# Create a controller with route attributes
php bin/lattice make:controller ContactController
# -> app/Http/ContactController.php

# Create an Eloquent model
php bin/lattice make:model Contact
# -> app/Models/Contact.php

# Create a validated DTO
php bin/lattice make:dto CreateContactDto
# -> app/Dto/CreateContactDto.php

# Create an authorization policy
php bin/lattice make:policy ContactPolicy
# -> app/Policies/ContactPolicy.php

# Create a workflow with activities
php bin/lattice make:workflow OrderFulfillment
# -> app/Workflows/OrderFulfillmentWorkflow.php
```

::: tip
Generated code includes the `#[Module]`, `#[Controller]`, `#[Workflow]`, and validation attributes pre-configured. You just need to fill in the business logic.
:::

## Development Server

```bash
# Start PHP built-in server (default: localhost:8000)
php bin/lattice serve

# Custom port
php bin/lattice serve --port=3000
```

## Database Commands

### Migrations

```bash
# Run all pending migrations
php bin/lattice migrate

# Rollback the last batch of migrations
php bin/lattice migrate:rollback

# Drop all tables and re-run all migrations
php bin/lattice migrate:fresh
```

::: danger
`migrate:fresh` drops ALL tables and recreates them. Never run this in production. Use `migrate:rollback` to undo specific migration batches instead.
:::

### Seeding

```bash
# Run all database seeders
php bin/lattice db:seed
```

## Route & Module Inspection

```bash
# List all registered routes with methods, paths, controllers, and guards
php bin/lattice route:list

# List all discovered modules with their imports, providers, and controllers
php bin/lattice module:list
```

`route:list` output shows:

```
+--------+------------------------+------------------------------------+------------------+
| Method | Path                   | Controller                         | Guards           |
+--------+------------------------+------------------------------------+------------------+
| GET    | /health                | HealthController@check             |                  |
| GET    | /api/contacts          | ContactController@index            | JwtAuth,Workspace|
| POST   | /api/contacts          | ContactController@store            | JwtAuth,Workspace|
| GET    | /api/contacts/:id      | ContactController@show             | JwtAuth,Workspace|
+--------+------------------------+------------------------------------+------------------+
```

## Queue Management

```bash
# Start processing jobs from the default queue
php bin/lattice queue:work

# Process jobs from a specific queue
php bin/lattice queue:work --queue=emails

# View and manage failed jobs
php bin/lattice queue:failed

# Monitor queue performance and depth
php bin/lattice queue:monitor
```

## Task Scheduling

```bash
# Run all due scheduled tasks (typically called from cron)
php bin/lattice schedule:run
```

Add this to your system crontab to run the scheduler every minute:

```
* * * * * cd /path-to-your-project && php bin/lattice schedule:run >> /dev/null 2>&1
```

See [Task Scheduling](scheduling.md) for defining scheduled tasks with the `#[Schedule]` attribute.

## Workflow Inspection

```bash
# List all registered workflows and activities
php bin/lattice workflow:list

# Check status of a workflow run
php bin/lattice workflow:status <workflow-id>
```

## Configuration Caching

```bash
# Cache all configuration for production
php bin/lattice config:cache

# Clear the configuration cache
php bin/lattice config:clear
```

## Route Caching

```bash
# Cache compiled route definitions for production
php bin/lattice route:cache

# Clear the route cache
php bin/lattice route:clear
```

::: tip
Always run `config:cache` and `route:cache` in your production deployment script. This eliminates file reads and reflection on every request.
:::

## OpenAPI Generation

```bash
# Generate an OpenAPI 3.1 specification from your annotated controllers
php bin/lattice openapi:generate

# Output to a specific file
php bin/lattice openapi:generate --output=public/openapi.json
```

This reads `#[ApiOperation]` and `#[ApiResponse]` attributes from your controllers and produces a complete OpenAPI 3.1 spec. See [OpenAPI Generation](openapi.md).

## Testing

```bash
# Run the full PHPUnit test suite
php bin/lattice test

# Run with a filter
php bin/lattice test --filter=test_create_contact
```

## Catalyst (AI Development)

```bash
# Install AI development tools (CLAUDE.md, .mcp.json, guidelines)
php bin/lattice catalyst:install

# Start MCP dev tools server for AI assistants
php bin/lattice catalyst:mcp

# Update project guidelines and metadata
php bin/lattice catalyst:update

# List and manage AI skills
php bin/lattice catalyst:skills
```

See [Catalyst](catalyst.md) for the full AI development accelerator guide.

## MCP Server

```bash
# Start the MCP server (stdio or SSE transport)
php bin/lattice mcp:serve

# List all registered MCP tools, resources, and prompts
php bin/lattice mcp:list
```

See [MCP Server](mcp.md) for exposing your application as tools for AI agents.

## WebSocket Server (Ripple)

```bash
# Start the WebSocket server
php bin/lattice ripple:serve

# List active channels
php bin/lattice ripple:channels

# Show active connections
php bin/lattice ripple:connections
```

See [WebSockets](websockets.md) for real-time event broadcasting.

## Deployment (Anvil)

```bash
# Deploy the application
php bin/lattice anvil:deploy

# Rollback to previous deployment
php bin/lattice anvil:rollback

# Check deployment status
php bin/lattice anvil:status
```

## Production Compilation

```bash
# Compile attribute manifest for zero-reflection runtime
php bin/lattice compile
```

::: warning
Always run `php bin/lattice compile` as part of your production deployment. This pre-scans all `#[Module]`, `#[Controller]`, `#[Workflow]`, and other attributes into a cached manifest. Without it, the framework uses runtime reflection which is slower.
:::

## Quick Reference

| Command | Purpose |
|---|---|
| `serve` | Start development server |
| `make:module` | Generate module class |
| `make:controller` | Generate controller class |
| `make:model` | Generate Eloquent model |
| `make:dto` | Generate validated DTO |
| `make:policy` | Generate authorization policy |
| `make:workflow` | Generate workflow + activity classes |
| `migrate` | Run pending migrations |
| `migrate:rollback` | Rollback last migration batch |
| `migrate:fresh` | Drop all tables and re-migrate |
| `db:seed` | Run database seeders |
| `route:list` | List all routes |
| `route:cache` | Cache routes for production |
| `route:clear` | Clear route cache |
| `module:list` | List all modules |
| `config:cache` | Cache config for production |
| `config:clear` | Clear config cache |
| `queue:work` | Process queue jobs |
| `queue:failed` | Manage failed jobs |
| `queue:monitor` | Monitor queue performance |
| `schedule:run` | Run due scheduled tasks |
| `workflow:list` | List workflows and activities |
| `workflow:status` | Check workflow run status |
| `openapi:generate` | Generate OpenAPI 3.1 spec |
| `compile` | Compile attribute manifest |
| `test` | Run PHPUnit tests |
| `catalyst:install` | Install AI development tools |
| `catalyst:mcp` | Start MCP dev tools server |
| `mcp:serve` | Start MCP server |
| `mcp:list` | List MCP tools/resources/prompts |
| `ripple:serve` | Start WebSocket server |
| `anvil:deploy` | Deploy application |
| `anvil:rollback` | Rollback deployment |
| `anvil:status` | Check deployment status |
