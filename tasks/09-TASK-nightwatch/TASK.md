# 09 — Nightwatch: Unified Monitoring

> Single monitoring tool for LatticePHP that auto-switches between debug inspector (dev) and metrics dashboard (prod), built on file-system NDJSON storage

## Dependencies
- `lattice/observability` (event hooks, span tracking, metric collection)
- `lattice/http` (HTTP kernel integration, request/response access)
- `lattice/pipeline` (middleware pipeline for watcher/recorder chain)
- Optional: `lattice/ripple` (for WebSocket real-time instead of SSE)

## Frontend Stack
- **Next.js** (React framework) or React SPA
- **NextUI** (component library)
- **TailwindCSS** (utility-first styling, dark/light theming)
- **TanStack Query** (server-state caching, automatic refetching)
- **TanStack Router** (if standalone SPA) or Next.js App Router
- **Zustand** (client-side state management — mode detection, filters, theme)
- **Zod** (runtime validation for API responses)

## Subtasks

### 1. [ ] File-system storage engine — NdjsonWriter, NdjsonReader, TimePartitioner, per-process temp files

#### NdjsonWriter
- Create `NdjsonWriter` class for append-only writes
- Accept array/object and serialize to a single JSON line
- Append to GZIP-compressed `.ndjson.gz` files using `gzopen` / `gzwrite`
- Handle file creation when target file does not yet exist
- Ensure atomic appends (no partial lines on concurrent writes)
- Flush and close handles properly to avoid data loss
- Support batched writes (multiple entries in one call to reduce I/O)
- Unit tests: single write, batch write, compression verification, concurrent append safety

#### NdjsonReader
- Create `NdjsonReader` class for reading compressed NDJSON files
- Decompress `.ndjson.gz` on the fly using `gzopen` / `gzgets`
- Parse each line as JSON and yield entries (generator-based for memory efficiency)
- Support filtering callback (predicate applied per-line during read)
- Support pagination (skip N entries, take M entries)
- Support reverse reading (newest first) for recent-entries queries
- Handle corrupted lines gracefully (skip and log, do not throw)
- Unit tests: read single file, read across multiple files, filter, paginate, corrupted line handling

#### TimePartitioner
- Create `TimePartitioner` class to resolve file paths from timestamps
- Map `DateTimeInterface` to `{type}/{Y}/{m}/{d}/{H}/events.ndjson.gz`
- Map metrics timestamps to `metrics/{Y}/{m}/{d}/{H}/aggregates.json`
- `pathsForRange(from, to, type)`: return all hourly paths within a time range
- `pathsForDay(date, type)`: return all hourly paths for a day
- `pathsOlderThan(cutoff, type)`: return paths for retention pruning
- Unit tests: path resolution, range spanning midnight, range spanning months, DST edge cases

#### Concurrency Strategy (Per-Process Temp Files)
- Each PHP-FPM worker writes to a process-specific temp file (`events.{pid}.ndjson`) to avoid lock contention
- Buffer entries in memory, flush every N entries or on request shutdown
- Background merge process: merge per-process files into hourly GZIP archive (runs every minute via scheduler)
- Index files track byte offsets per entry for efficient reverse-chronological reads
- Unit tests for per-process isolation and merge correctness

- **Verify:** `NdjsonWriter` produces valid GZIP-compressed NDJSON; `NdjsonReader` reads back all entries including across multiple files; `TimePartitioner` maps timestamps to correct paths; per-process temp files avoid write contention

### 2. [ ] StorageManager + RetentionManager + Aggregator

#### StorageManager
- Create `StorageManager` class orchestrating reads and writes
- Accept configurable base path (default: `storage/nightwatch/`)
- `store(type, entry)`: resolve path via TimePartitioner, write via NdjsonWriter
- `storeBatch(type, entries)`: batch variant for multiple entries
- `query(type, from, to, filter, limit, offset)`: read entries across hourly files
- `queryLatest(type, count)`: shortcut for most recent entries
- Lazy-load file handles to avoid opening unnecessary files
- Ensure base directory and subdirectories are created on first write
- Unit tests: store and retrieve round-trip, time range queries, latest queries, directory auto-creation

#### RetentionManager
- Create `RetentionManager` class for pruning old data
- Accept configurable TTL per type (default: 7 days dev, 90 days prod)
- `prune(type)`: delete directories older than TTL, return count deleted
- `pruneAll()`: prune all types, return summary
- Safely handle concurrent access (do not delete directories being written to)
- Log pruning actions for auditability
- Unit tests: prune old directories, respect TTL, skip current hour, type-specific pruning

#### Aggregator
- Create `Aggregator` class to bucket raw entries into time-series aggregates
- Support bucket sizes: 1 minute, 5 minutes, 1 hour, 1 day
- Compute percentiles (P50, P95, P99) for latency-type metrics
- Compute counts, sums, averages, min, max for general metrics
- Compute ratios (e.g., cache hit ratio) from hit/miss counts
- Store aggregates in `metrics/{Y}/{m}/{d}/{H}/aggregates.json`
- Support incremental aggregation (update existing bucket without reprocessing)
- Roll up finer buckets into coarser buckets (1min -> 5min -> 1hr -> 1day)
- Unit tests: percentile calculation, incremental update, rollup accuracy, empty data handling

#### Configuration
- Create `NightwatchConfig`: storage_path, mode (auto/dev/prod), retention (dev: 7d, prod: 90d), aggregation_intervals, sampling_rate, enabled toggle, watchers map, recorders map
- Publish config via `php lattice vendor:publish --tag=nightwatch-config`

#### NightwatchServiceProvider
- Register `StorageManager`, `RetentionManager`, `Aggregator`, `TimePartitioner` as singletons
- Conditionally register dev-mode watchers or prod-mode recorders based on resolved mode
- Boot watchers/recorders (hook into framework events)
- Register config file, CLI commands, web routes
- Provide `Nightwatch` facade: `record()`, `query()`, `prune()`, `toggle()`, `isEnabled()`
- Unit tests for provider registration and mode-based conditional loading

- **Verify:** `StorageManager` stores and retrieves entries across time-partitioned files; `RetentionManager` prunes old data respecting TTL; `Aggregator` computes accurate percentiles and rollups

### 3. [ ] Dev mode watchers — request, query, exception, event, cache, job, mail, log, model, gate

#### Base Classes
- Create abstract `Watcher` base class: `enabled`, `shouldRecord(entry)` filter, `record(entry)` method
- Create `EntryType` enum: `request`, `query`, `exception`, `event`, `cache`, `job`, `mail`, `log`, `model`, `gate`
- Create `Entry` value object: `type`, `timestamp`, `uuid`, `data`, `tags`, `batchId`
- Create `BatchManager` to group entries by request/job lifecycle

#### RequestWatcher
- Hook into HTTP kernel (before/after middleware)
- Capture: method, URI, route name, controller, headers, IP, session ID, response status, size, content type, duration (ms), authenticated user, middleware list
- Redact sensitive headers (Authorization, Cookie) by default
- Configurable ignored paths (e.g., `/nightwatch/*`, health checks)
- Tests: GET/POST capture, header redaction, path ignoring, duration accuracy

#### QueryWatcher
- Hook into database query event
- Capture: raw SQL, bindings, duration (ms), connection name, caller file:line (backtrace)
- Flag slow queries (configurable threshold, default 100ms)
- Detect N+1 query patterns (same query repeated in loop)
- Capture query type (SELECT, INSERT, UPDATE, DELETE)
- Tests: SQL capture, binding interpolation, slow flag, N+1 detection, caller resolution

#### ExceptionWatcher
- Hook into exception handler
- Capture: class, message, code, full stack trace, request context, previous exception chain
- Support custom context from exceptions implementing a context interface
- Configurable ignored exception classes
- Tests: basic exception, nested exception, request context, ignore list

#### EventWatcher
- Hook into event dispatcher
- Capture: event class, payload, listener list, broadcast flag
- Configurable ignored event classes (framework internals)
- Tests: event capture, listener list, ignore list

#### CacheWatcher
- Hook into cache events (hit, miss, write, forget)
- Capture: operation type, key, TTL (on write), value size, store name, duration
- Configurable ignored key patterns
- Tests: hit/miss/write/forget capture, key filtering

#### JobWatcher
- Hook into queue job lifecycle events
- Capture: job class, queue, connection, payload (size-limited), status, duration, attempt number, max tries, exception on failure
- Tests: full lifecycle, failure with exception, retry tracking

#### MailWatcher
- Hook into mail sending event
- Capture: to, cc, bcc, subject, from, mailable class, rendered HTML body (stored separately), attachments list, queued flag
- Store HTML preview in `mail/{Y}/{m}/{d}/{H}/{uuid}.html.gz`
- Tests: metadata, HTML preview storage/retrieval, queued flag

#### LogWatcher
- Hook into log handler
- Capture: level, message, context array, channel name
- Configurable minimum level (default: debug in dev)
- Tests: level capture, context, minimum level filtering

#### ModelWatcher
- Hook into model events (created, updated, deleted)
- Capture: model class, primary key, operation, changed attributes with old/new values
- Redact sensitive attributes (password, secret)
- Configurable: watch specific models or exclude models
- Tests: create/update/delete, attribute diff, sensitive redaction

#### GateWatcher
- Hook into authorization gate checks
- Capture: ability name, result (allowed/denied), user ID/class, arguments, policy class/method
- Tests: allow/deny, policy resolution, argument capture

- **Verify:** Each watcher captures correct data for its event type; sensitive data is redacted; configurable ignore lists filter unwanted entries; all entries include batch IDs linking related entries within a request

### 4. [ ] Prod mode recorders — request, query, exception, cache, queue, server (aggregated)

#### Base Recorder
- Create abstract `Recorder` base class: `enabled`, `sample()` probabilistic check, `record(entry)` aggregation method

#### RequestRecorder
- Hook into HTTP kernel
- Compute running percentiles: P99, P95, P50 for response time
- Track status code distribution (2xx, 3xx, 4xx, 5xx counts)
- Group by endpoint (route name or URI pattern)
- Track requests per minute
- Feed into Aggregator
- Tests: percentile accuracy, status distribution, endpoint grouping

#### QueryRecorder
- Hook into database query event
- Normalize SQL (strip literal values) for grouping
- Track slow query frequency by normalized SQL
- Track average and P95 duration per normalized query
- Track total query count per interval
- Tests: SQL normalization, frequency counting, duration tracking

#### ExceptionRecorder
- Hook into exception handler
- Count exceptions by class name
- Track trend (increasing/decreasing/stable) over rolling window
- Track first seen / last seen per exception class
- Tests: counting, trend detection, first/last seen

#### CacheRecorder
- Hook into cache events
- Compute hit ratio: hits / (hits + misses) per interval
- Track total operations per interval, cache store breakdown
- Tests: ratio calculation, store breakdown

#### QueueRecorder
- Hook into queue job lifecycle
- Track throughput (jobs processed per interval), avg wait time, failure rate
- Group by queue name
- Tests: throughput, wait time, failure rate, queue grouping

#### ServerRecorder
- Probe CPU (`/proc/stat` on Linux, fallback for macOS/Windows), memory (`/proc/meminfo` or `free`), disk (`disk_free_space()`)
- Run probes on configurable interval (default: 15 seconds)
- Feed into Aggregator
- Tests: probe parsing, fallback behavior, interval timing

#### Mode Switching
- Auto-switch logic: `APP_ENV=local/dev` -> dev mode, `APP_ENV=production/staging` -> prod mode
- Manual override via `NIGHTWATCH_MODE` env var or config
- Watchers and recorders are mutually exclusive (only one set active)
- Configurable sampling rate for prod mode (default: 1.0)
- Tests: auto-switch, manual override, sampling rate

- **Verify:** Prod recorders produce aggregated metrics without storing individual entries; percentiles are accurate; SQL normalization groups equivalent queries; mode auto-switches based on `APP_ENV`

### 5. [ ] API layer — NightwatchModule, dev endpoints (entries per type, batch view)

#### NightwatchModule
- Create `NightwatchModule` extending Module class
- Register routes under configurable prefix (default: `/nightwatch`)
- Apply admin guard middleware (configurable: auth, IP whitelist, custom)
- Include CORS headers for SPA consumption
- Tests: route registration, guard enforcement, CORS headers

#### Dev Mode Endpoints
- `GET /nightwatch/api/requests` — paginated request list (filter: status, method, URI, duration)
- `GET /nightwatch/api/requests/{uuid}` — single request detail with related entries
- `GET /nightwatch/api/queries` — paginated query list (filter: slow, duration, SQL pattern)
- `GET /nightwatch/api/queries/{uuid}` — single query detail
- `GET /nightwatch/api/exceptions` — paginated exception list (filter: class, message)
- `GET /nightwatch/api/exceptions/{uuid}` — exception detail with stack trace
- `GET /nightwatch/api/events` — paginated event list
- `GET /nightwatch/api/events/{uuid}` — event detail with listeners
- `GET /nightwatch/api/cache` — paginated cache operations (filter: hit/miss/write/forget)
- `GET /nightwatch/api/jobs` — paginated job list (filter: status, queue)
- `GET /nightwatch/api/jobs/{uuid}` — job detail
- `GET /nightwatch/api/mail` — paginated mail list
- `GET /nightwatch/api/mail/{uuid}` — mail detail
- `GET /nightwatch/api/mail/{uuid}/html` — rendered HTML preview (raw HTML response)
- `GET /nightwatch/api/logs` — paginated log list (filter: level, channel, message)
- `GET /nightwatch/api/models` — paginated model changes (filter: model class, operation)
- `GET /nightwatch/api/gates` — paginated gate checks (filter: ability, result)
- `GET /nightwatch/api/batch/{batchId}` — all entries in a request/job batch (timeline view)
- All list endpoints support `from`, `to`, `limit`, `offset` parameters
- Tests: each endpoint with valid/invalid params, filtering, pagination, time range

#### Control Endpoints
- `POST /nightwatch/api/toggle` — enable/disable monitoring
- `POST /nightwatch/api/prune` — trigger retention pruning, return summary
- `GET /nightwatch/api/status` — current mode, enabled state, storage size, entry counts
- Tests: toggle persistence, prune execution, status accuracy

- **Verify:** Dev API endpoints return paginated entries for each type; batch endpoint groups related entries from a single request; control endpoints toggle monitoring and trigger pruning

### 6. [ ] API — prod endpoints (overview, slow requests/queries, exceptions, cache, queue, servers)

- `GET /nightwatch/api/overview` — dashboard summary (all key metrics for current period)
- `GET /nightwatch/api/slow-requests` — top slow endpoints by P95 latency
- `GET /nightwatch/api/slow-queries` — top slow queries by frequency and duration
- `GET /nightwatch/api/exception-counts` — top exceptions by count with trend
- `GET /nightwatch/api/cache-ratio` — cache hit ratio time series
- `GET /nightwatch/api/queue-throughput` — queue throughput time series
- `GET /nightwatch/api/servers` — server vitals time series (CPU, memory, disk)
- All prod endpoints support `period` parameter (1h, 6h, 24h, 7d, 30d)
- All prod endpoints support `resolution` parameter (1min, 5min, 1hr, 1day — auto-selected based on period)
- Tests: each endpoint, period/resolution combinations, empty data handling
- **Verify:** Prod endpoints return aggregated metrics; slow-requests shows top endpoints by P95; exception-counts includes trend direction; server vitals return CPU/memory/disk time series

### 7. [ ] Frontend — unified dashboard (auto-switch dev/prod), dev entry views, prod metric cards

#### Project Setup
- Initialize Next.js project with TypeScript in `projects/nightwatch/frontend/`
- Install and configure NextUI, TailwindCSS (dark/light), TanStack Query, Zustand, Zod
- Configure path aliases, ESLint + Prettier, Vitest
- Configure development proxy to LatticePHP backend
- Configure production build output

#### API Client and Mode Detection
- Create typed HTTP client using TanStack Query
- Define Zod schemas for all dev and prod API response types
- Create query hooks for each endpoint
- Auto-detect mode (dev/prod) from `GET /nightwatch/api/status` endpoint, store in Zustand

#### Shell and Navigation
- Top navigation bar: mode indicator (NextUI `Chip`), time range picker, refresh button, toggle switch
- Sidebar navigation: entry types (dev) or metric categories (prod) — dynamically switched based on mode
- Dark/light theme toggle with system preference detection (dark-first for dev tools)
- Responsive layout (desktop sidebar, mobile bottom nav or hamburger)

#### Dev View — Entry Lists and Details
- **Request list**: table with method, URI, status (color-coded NextUI `Chip`), duration (color-coded), timestamp; click opens detail panel with headers, payload, related queries/logs/exceptions
- **Query list**: SQL (truncated), duration, connection, slow badge, N+1 badge; detail panel with full SQL, bindings, caller file:line
- **Exception list**: class, message, count, last occurrence; grouped by class; detail with stack trace (collapsible frames, vendor dimming)
- **Event list**: event class, listener count, broadcast flag; detail with payload and listener list
- **Cache stats**: operation, key, store, duration; color-coded badges (green hit, red miss, blue write, gray forget)
- **Job list**: job class, queue, status (color-coded), duration; detail with payload, exception, attempts
- **Mail previewer**: to, subject, mailable class; detail with headers, HTML preview in sandboxed iframe
- **Log viewer**: level (color-coded badge), message, channel; live tail mode (auto-scroll)
- **Model changes**: model class, key, operation; detail with attribute diff (old vs new highlighted)
- **Gate checks**: ability, result (color-coded), user; detail with arguments, policy info
- **Batch/timeline view**: visual timeline of all entries in a single request/job lifecycle, ordered by timestamp, color-coded by type, duration bars

#### Prod View — Metric Dashboards
- **Overview dashboard**: card grid with key metrics (NextUI `Card`), each with title, current value, trend indicator, sparkline; cards: requests/min, avg response time, P99, error rate, slow queries, cache hit ratio, queue throughput, CPU, memory, disk
- **Slow requests**: table with endpoint, P95, P99, avg, request count; sparkline per endpoint
- **Slow queries**: normalized SQL, avg duration, P95, frequency; SQL syntax highlighting
- **Exceptions**: class, count, trend indicator, first/last seen; click for occurrence chart
- **Cache**: hit ratio over time line chart; breakdown by store
- **Queue**: throughput over time chart; table with queue name, processed, failed, failure rate, avg wait
- **Server vitals**: gauge charts for CPU/memory/disk; line charts for historical trends

#### Responsive Design
- All filter bars collapse on mobile
- Tables switch to card layout on small screens
- Charts responsive to container width
- Detail panels become full-screen on mobile

- **Verify:** SPA auto-detects dev/prod mode and renders the appropriate interface; dev view shows entry lists with filtering and detail panels; prod view shows metric cards with sparklines and time-series charts

### 8. [ ] CLI TUI — `php lattice nightwatch` interactive + `--tail` live mode + non-interactive commands

#### Interactive TUI
- `php lattice nightwatch` — launch interactive TUI
- Auto-detect dev/prod mode, show appropriate interface
- Keyboard-driven navigation (arrow keys, tab, enter, escape)
- Graceful terminal resize handling, clean exit on Ctrl+C / q

#### Dev Mode TUI
- Tab bar for entry types (requests, queries, exceptions, events, cache, jobs, mail, logs)
- Scrollable entry list per tab with column-aligned display
- Detail panel (split pane or overlay) on Enter
- Slow query highlighting, stack trace viewer with scrollable frames
- Log viewer with level-based coloring
- Search/filter within current list
- Keyboard shortcuts: `r` requests, `q` queries, `e` exceptions, `j` jobs, `l` logs, `?` help

#### Prod Mode TUI
- Metrics cards with current values and trend arrows
- Sparkline charts using Unicode block characters
- CPU/memory/disk gauges (horizontal bar charts, htop-style)
- P99/P95/P50 latency table, slow query table, top exceptions table
- Queue throughput summary
- Auto-refresh on configurable interval (default: 5 seconds)

#### Live Tail Mode
- `php lattice nightwatch --tail` — stream new entries as they are written
- Color-coded output by entry type
- Compact single-line format per entry
- Filter by type: `--tail --type=queries`
- Filter by level: `--tail --level=error` (for logs)

#### Non-Interactive CLI Commands
- `php lattice nightwatch:requests` — list recent requests (`--limit`, `--status`, `--slow`, `--from`/`--to`, `--json`)
- `php lattice nightwatch:queries` — list recent queries (`--slow`, `--limit`, `--sort=duration|count`, `--json`)
- `php lattice nightwatch:exceptions` — list recent exceptions (`--limit`, `--class`, `--json`)
- `php lattice nightwatch:logs` — list recent logs (`--level`, `--channel`, `--limit`, `--json`)
- `php lattice nightwatch:metrics` — display prod metrics summary (`--period`, `--json`)
- `php lattice nightwatch:servers` — display server vitals (`--watch`, `--json`)
- `php lattice nightwatch:prune` — run retention pruning (`--type`, `--before`, `--dry-run`, `--force`)
- `php lattice nightwatch:toggle` — toggle monitoring (`--on`, `--off`)
- Unit tests for all CLI commands with all flag combinations

- **Verify:** `php lattice nightwatch` TUI auto-detects mode and renders appropriate interface; `--tail` streams new entries in real-time; non-interactive commands output correctly in both table and JSON formats

### 9. [ ] Tests — storage engine tests, watcher tests, recorder tests, API tests

#### Storage Engine Tests
- NdjsonWriter: write, batch write, compression, concurrent safety
- NdjsonReader: read, decompress, filter, paginate, corrupted lines
- TimePartitioner: path resolution, ranges, edge cases
- StorageManager: round-trip, queries, auto-creation
- RetentionManager: pruning, TTL, concurrent safety
- Aggregator: percentiles, incremental, rollup

#### Watcher Tests
- RequestWatcher: all HTTP methods, header redaction, path ignoring
- QueryWatcher: SQL capture, slow detection, N+1 detection
- ExceptionWatcher: basic, nested, context
- EventWatcher: capture, listeners, ignore list
- CacheWatcher: all operations
- JobWatcher: full lifecycle, failure
- MailWatcher: metadata, HTML preview
- LogWatcher: levels, context, minimum level
- ModelWatcher: CRUD, attribute diff, redaction
- GateWatcher: allow/deny, policy

#### Recorder Tests
- RequestRecorder: percentiles, status distribution
- QueryRecorder: SQL normalization, frequency
- ExceptionRecorder: counting, trends
- CacheRecorder: hit ratio
- QueueRecorder: throughput, failure rate
- ServerRecorder: probe parsing, fallback

#### API Tests
- All dev endpoints: list, detail, filter, pagination
- All prod endpoints: overview, time series, period/resolution
- Control endpoints: toggle, prune, status
- Authorization: IP check, user check, 403

#### CLI Tests
- Each non-interactive command with all flag combinations
- Output format (table, JSON)
- Prune dry-run vs actual
- Toggle state persistence

#### E2E Tests
- Full request lifecycle: make HTTP request -> entries stored -> query via API -> display in CLI
- Mode switching: change APP_ENV -> correct watchers/recorders activate
- Retention: create old data -> prune -> verify deletion
- Aggregation: store raw entries -> aggregate -> verify bucketed output

- **Verify:** All storage engine tests pass (NDJSON write/read round-trip, compression, partitioning); all watcher tests pass (each captures correct data); all recorder tests pass (aggregations are accurate); API tests confirm correct responses for both dev and prod endpoints

## Integration Verification
- [ ] NDJSON files write and read correctly with GZIP compression
- [ ] `RequestWatcher` captures a full HTTP request including headers, duration, and response status
- [ ] `QueryWatcher` flags slow queries and detects N+1 patterns
- [ ] `ExceptionWatcher` captures exception with full stack trace and request context
- [ ] Prod mode `RequestRecorder` computes accurate P95/P99 latencies
- [ ] `Aggregator` rolls up 1-minute buckets into hourly buckets correctly
- [ ] `RetentionManager` prunes directories older than configured TTL
- [ ] Dev API endpoints return paginated entries with filtering working correctly
- [ ] Prod API endpoints return aggregated metrics with correct time-series data
- [ ] SPA auto-detects mode and renders the appropriate interface (dev entry views or prod metric cards)
- [ ] `php lattice nightwatch --tail` streams new entries as they are generated by the application
- [ ] End-to-end: make HTTP requests to the app, see entries appear in Nightwatch dev view, switch to prod mode and see aggregated metrics
