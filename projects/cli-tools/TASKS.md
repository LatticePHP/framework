# CLI Tools — Task List

## route:cache

- [ ] Implement `RouteCacheCommand` class
- [ ] Collect all registered routes from the router
- [ ] Serialize route collection to a PHP array manifest
- [ ] Write manifest to `bootstrap/cache/routes.php`
- [ ] Ensure router reads from cache file when it exists
- [ ] Handle edge cases: closure-based routes (cannot be cached — warn and skip)
- [ ] Add `--format` option (php, json)
- [ ] Write unit tests for route caching logic
- [ ] Write integration test: cache, boot from cache, verify routes match

## route:list

- [ ] Implement `RouteListCommand` class
- [ ] Display table with columns: Method, URI, Name, Middleware, Controller@Action
- [ ] Add `--method` filter (e.g., `--method=GET`)
- [ ] Add `--path` filter (e.g., `--path=api/*`)
- [ ] Add `--name` filter
- [ ] Add `--json` output option
- [ ] Add `--sort` option (uri, name, method)
- [ ] Handle routes with multiple HTTP methods
- [ ] Write unit tests

## route:clear

- [ ] Implement `RouteClearCommand` class
- [ ] Delete `bootstrap/cache/routes.php` if it exists
- [ ] Print confirmation message
- [ ] Handle case where no cache exists (no-op with info message)
- [ ] Write unit tests

## openapi:generate

- [ ] Implement `OpenApiGenerateCommand` class
- [ ] Scan routes for OpenAPI metadata attributes
- [ ] Generate OpenAPI 3.1 spec from collected metadata
- [ ] Support `--output` option for file path (default: stdout)
- [ ] Support `--format` option (json, yaml)
- [ ] Include request body schemas from validation rules
- [ ] Include response schemas from return type annotations
- [ ] Include authentication schemes from route guards
- [ ] Validate generated spec against OpenAPI 3.1 schema
- [ ] Write unit tests
- [ ] Write integration test with real annotated routes

## config:cache

- [ ] Implement `ConfigCacheCommand` class
- [ ] Merge all configuration files into a single array
- [ ] Write merged config to `bootstrap/cache/config.php`
- [ ] Ensure application reads from cache file when it exists
- [ ] Handle environment variable references (resolve at cache time)
- [ ] Warn if `.env` values are used in config (they won't update after caching)
- [ ] Write unit tests
- [ ] Write integration test: cache, boot from cache, verify config matches

## config:clear

- [ ] Implement `ConfigClearCommand` class
- [ ] Delete `bootstrap/cache/config.php` if it exists
- [ ] Print confirmation message
- [ ] Handle case where no cache exists
- [ ] Write unit tests

## module:list

- [ ] Implement `ModuleListCommand` class
- [ ] Display table with columns: Module, Version, Status (enabled/disabled), Dependencies
- [ ] Show dependency graph as tree when `--tree` flag is used
- [ ] Detect circular dependencies and warn
- [ ] Add `--json` output option
- [ ] Write unit tests

## workflow:list

- [ ] Implement `WorkflowListCommand` class
- [ ] Query workflow registry for all registered workflow definitions
- [ ] Display table with columns: Name, Class, Activities, Signals
- [ ] Add `--json` output option
- [ ] Write unit tests

## workflow:status

- [ ] Implement `WorkflowStatusCommand` class accepting a workflow run ID argument
- [ ] Query workflow event store for the run's event history
- [ ] Display current status (running, completed, failed, paused)
- [ ] Display timeline of events (activity started, completed, failed, signals)
- [ ] Display current pending activities
- [ ] Display error details if workflow failed
- [ ] Add `--json` output option
- [ ] Handle invalid/unknown workflow ID gracefully
- [ ] Write unit tests

## queue:monitor

- [ ] Implement `QueueMonitorCommand` class
- [ ] Display table with columns: Queue Name, Size, Failed, Workers
- [ ] Support multiple queue connections
- [ ] Add `--watch` option for continuous refresh (poll interval)
- [ ] Add `--json` output option
- [ ] Write unit tests

## General

- [ ] Register all new commands in their respective module service providers
- [ ] Verify all commands appear in `php lattice list` output
- [ ] Verify `--help` works for every command
- [ ] Ensure consistent exit codes (0 = success, 1 = error)
- [ ] Add command descriptions to framework documentation
