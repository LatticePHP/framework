# 04 — CLI Tools

> Implement all `php lattice` console commands for route caching, OpenAPI generation, config caching, module/workflow inspection, and queue monitoring

## Dependencies
- None (Wave 1)
- Packages: `packages/routing/`, `packages/openapi/`, `packages/module/`, `packages/workflow/`, `packages/workflow-store/`, `packages/queue/`, `packages/core/`

## Subtasks

### 1. [ ] `route:cache` and `route:clear`
- Implement `RouteCacheCommand` class
- Collect all registered routes from the router
- Serialize route collection to a PHP array manifest
- Write manifest to `bootstrap/cache/routes.php`
- Ensure router reads from cache file when it exists
- Handle edge cases: closure-based routes (cannot be cached — warn and skip)
- Add `--format` option (php, json)
- Implement `RouteClearCommand` class
- Delete `bootstrap/cache/routes.php` if it exists
- Print confirmation message; handle case where no cache exists (no-op with info message)
- Write unit tests for both commands
- Write integration test: cache, boot from cache, verify routes match
- **Verify:** `php lattice route:cache` creates cache, `php lattice route:clear` removes it, app boots from cache correctly

### 2. [ ] `route:list`
- Implement `RouteListCommand` class
- Display table with columns: Method, URI, Name, Middleware, Controller@Action
- Add `--method` filter (e.g., `--method=GET`)
- Add `--path` filter (e.g., `--path=api/*`)
- Add `--name` filter
- Add `--json` output option
- Add `--sort` option (uri, name, method)
- Handle routes with multiple HTTP methods
- Write unit tests
- **Verify:** `php lattice route:list` displays all routes; filters and JSON output work correctly

### 3. [ ] `openapi:generate`
- Implement `OpenApiGenerateCommand` class
- Scan routes for OpenAPI metadata attributes
- Generate OpenAPI 3.1 spec from collected metadata
- Support `--output` option for file path (default: stdout)
- Support `--format` option (json, yaml)
- Include request body schemas from validation rules
- Include response schemas from return type annotations
- Include authentication schemes from route guards
- Validate generated spec against OpenAPI 3.1 schema
- Write unit tests
- Write integration test with real annotated routes
- **Verify:** `php lattice openapi:generate` outputs valid OpenAPI 3.1 spec; `--output` writes to file

### 4. [ ] `config:cache` and `config:clear`
- Implement `ConfigCacheCommand` class
- Merge all configuration files into a single array
- Write merged config to `bootstrap/cache/config.php`
- Ensure application reads from cache file when it exists
- Handle environment variable references (resolve at cache time)
- Warn if `.env` values are used in config (they won't update after caching)
- Implement `ConfigClearCommand` class
- Delete `bootstrap/cache/config.php` if it exists; handle no-cache case
- Write unit tests for both commands
- Write integration test: cache, boot from cache, verify config matches
- **Verify:** `php lattice config:cache` creates cache, `php lattice config:clear` removes it, app boots from cache correctly

### 5. [ ] `module:list`
- Implement `ModuleListCommand` class
- Display table with columns: Module, Version, Status (enabled/disabled), Dependencies
- Show dependency graph as tree when `--tree` flag is used
- Detect circular dependencies and warn
- Add `--json` output option
- Write unit tests
- **Verify:** `php lattice module:list` displays all registered modules; `--tree` shows dependency graph

### 6. [ ] `workflow:list`
- Implement `WorkflowListCommand` class
- Query workflow registry for all registered workflow definitions
- Display table with columns: Name, Class, Activities, Signals
- Add `--json` output option
- Write unit tests
- **Verify:** `php lattice workflow:list` displays all registered workflows with correct metadata

### 7. [ ] `workflow:status <id>`
- Implement `WorkflowStatusCommand` class accepting a workflow run ID argument
- Query workflow event store for the run's event history
- Display current status (running, completed, failed, paused)
- Display timeline of events (activity started, completed, failed, signals)
- Display current pending activities
- Display error details if workflow failed
- Add `--json` output option
- Handle invalid/unknown workflow ID gracefully
- Write unit tests
- **Verify:** `php lattice workflow:status <id>` shows correct status, timeline, and pending activities

### 8. [ ] `queue:monitor`
- Implement `QueueMonitorCommand` class
- Display table with columns: Queue Name, Size, Failed, Workers
- Support multiple queue connections
- Add `--watch` option for continuous refresh (poll interval)
- Add `--json` output option
- Write unit tests
- **Verify:** `php lattice queue:monitor` shows queue stats; `--watch` refreshes continuously

### 9. [ ] Register all commands in console kernel
- Register all new commands in their respective module service providers
- Verify all commands appear in `php lattice list` output
- Verify `--help` works for every command
- Ensure consistent exit codes (0 = success, 1 = error)
- **Verify:** `php lattice list` shows all commands; `php lattice <cmd> --help` works for each

### 10. [ ] Documentation for all commands
- Add command descriptions and usage examples to framework documentation
- Document all options and flags for each command
- Include example output for each command
- **Verify:** Documentation is accurate and examples are runnable

## Integration Verification
- [ ] All commands registered and visible in `php lattice list`
- [ ] Each command returns exit code 0 on success, 1 on error
- [ ] `php lattice route:cache && php lattice route:list` works from cached routes
- [ ] `php lattice openapi:generate --format=yaml --output=openapi.yaml` produces valid spec
- [ ] Full test suite passes: `make test-suite S=Console` (or equivalent)
