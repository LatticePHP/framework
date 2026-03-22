# CLI Tools — Missing Framework Commands

## Overview

Build the missing CLI commands for the LatticePHP framework. These commands cover route management, OpenAPI spec generation, configuration caching, module inspection, workflow monitoring, and queue monitoring. Each command follows the existing LatticePHP CLI conventions and integrates with the module system.

## Scope

| Command | Package | Purpose |
|---|---|---|
| `php lattice route:cache` | `lattice/routing` | Compile routes to a cached manifest file |
| `php lattice route:list` | `lattice/routing` | Display all registered routes in a table |
| `php lattice route:clear` | `lattice/routing` | Remove the route cache file |
| `php lattice openapi:generate` | `lattice/openapi` | Generate OpenAPI 3.1 spec from route metadata |
| `php lattice config:cache` | `lattice/core` | Compile all configuration into a single cached file |
| `php lattice config:clear` | `lattice/core` | Remove the configuration cache file |
| `php lattice module:list` | `lattice/module` | Show all registered modules and their dependency graph |
| `php lattice workflow:list` | `lattice/workflow` | Show all registered workflow definitions |
| `php lattice workflow:status <id>` | `lattice/workflow` | Show execution status of a specific workflow run |
| `php lattice queue:monitor` | `lattice/queue` | Display queue sizes and worker status |

## Success Criteria

1. Every command listed above is implemented, tested, and documented.
2. Commands follow the existing CLI pattern (consistent output formatting, exit codes, help text).
3. `--help` works for every command.
4. Commands are registered via their respective modules so they appear in `php lattice list`.

## Dependencies

| Package | Role |
|---|---|
| `lattice/core` | CLI infrastructure, application bootstrap |
| `lattice/routing` | Route collection access for route commands |
| `lattice/openapi` | OpenAPI spec builder |
| `lattice/module` | Module registry for module:list |
| `lattice/workflow` | Workflow registry and event store for workflow commands |
| `lattice/queue` | Queue connection inspection for queue:monitor |
