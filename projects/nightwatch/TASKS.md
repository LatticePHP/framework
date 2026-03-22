# Nightwatch — Task Breakdown

---

## Phase 1 — File-System Storage Engine

The foundation: a high-performance, append-only storage engine built on NDJSON + GZIP with time-based partitioning.

### NdjsonWriter
- [ ] Create `NdjsonWriter` class for append-only writes
- [ ] Accept an array/object and serialize to a single JSON line
- [ ] Append to GZIP-compressed `.ndjson.gz` files using `gzopen` / `gzwrite`
- [ ] Handle file creation when the target file does not yet exist
- [ ] Ensure atomic appends (no partial lines on concurrent writes)
- [ ] Flush and close handles properly to avoid data loss
- [ ] Support batched writes (multiple entries in one call to reduce I/O)
- [ ] Unit tests: single write, batch write, compression verification, concurrent append safety

### NdjsonReader
- [ ] Create `NdjsonReader` class for reading compressed NDJSON files
- [ ] Decompress `.ndjson.gz` on the fly using `gzopen` / `gzgets`
- [ ] Parse each line as JSON and yield entries (generator-based for memory efficiency)
- [ ] Support filtering callback (predicate applied per-line during read)
- [ ] Support pagination (skip N entries, take M entries)
- [ ] Support reverse reading (newest first) for recent-entries queries
- [ ] Handle corrupted lines gracefully (skip and log, do not throw)
- [ ] Unit tests: read single file, read across multiple files, filter, paginate, corrupted line handling

### TimePartitioner
- [ ] Create `TimePartitioner` class to resolve file paths from timestamps
- [ ] Map a `DateTimeInterface` to `{type}/{Y}/{m}/{d}/{H}/events.ndjson.gz`
- [ ] Map metrics timestamps to `metrics/{Y}/{m}/{d}/{H}/aggregates.json`
- [ ] Provide `pathsForRange(DateTimeInterface $from, DateTimeInterface $to, string $type): array` — return all hourly paths within a time range
- [ ] Provide `pathsForDay(DateTimeInterface $date, string $type): array`
- [ ] Provide `pathsOlderThan(DateTimeInterface $cutoff, string $type): array` — for retention pruning
- [ ] Unit tests: path resolution, range spanning midnight, range spanning months, DST edge cases

### StorageManager
- [ ] Create `StorageManager` class to orchestrate reads and writes
- [ ] Accept configurable base path (default: `storage/nightwatch/`)
- [ ] `store(string $type, array $entry): void` — resolve path via TimePartitioner, write via NdjsonWriter
- [ ] `storeBatch(string $type, array $entries): void` — batch variant
- [ ] `query(string $type, DateTimeInterface $from, DateTimeInterface $to, ?callable $filter, int $limit, int $offset): array` — read entries across hourly files
- [ ] `queryLatest(string $type, int $count): array` — shortcut for most recent entries
- [ ] Lazy-load file handles to avoid opening unnecessary files
- [ ] Ensure base directory and subdirectories are created on first write
- [ ] Unit tests: store and retrieve round-trip, time range queries, latest queries, directory auto-creation

### RetentionManager
- [ ] Create `RetentionManager` class for pruning old data
- [ ] Accept configurable TTL per type (default: 7 days dev, 90 days prod)
- [ ] `prune(?string $type = null): int` — delete directories older than TTL, return count of deleted directories
- [ ] `pruneAll(): array` — prune all types, return summary
- [ ] Safely handle concurrent access (do not delete directories being written to)
- [ ] Log pruning actions for auditability
- [ ] Unit tests: prune old directories, respect TTL, skip current hour, type-specific pruning

### Aggregator
- [ ] Create `Aggregator` class to bucket raw entries into time-series aggregates
- [ ] Support bucket sizes: 1 minute, 5 minutes, 1 hour, 1 day
- [ ] Compute percentiles (P50, P95, P99) for latency-type metrics
- [ ] Compute counts, sums, averages, min, max for general metrics
- [ ] Compute ratios (e.g., cache hit ratio) from hit/miss counts
- [ ] Store aggregates in `metrics/{Y}/{m}/{d}/{H}/aggregates.json`
- [ ] Support incremental aggregation (update existing bucket without reprocessing all raw data)
- [ ] Roll up finer buckets into coarser buckets (1min -> 5min -> 1hr -> 1day)
- [ ] Unit tests: percentile calculation, incremental update, rollup accuracy, empty data handling

### Configuration
- [ ] Create `NightwatchConfig` class (or use array config)
- [ ] `storage_path` — base directory (default: `storage/nightwatch/`)
- [ ] `mode` — `auto`, `dev`, or `prod` (default: `auto` — inferred from `APP_ENV`)
- [ ] `retention.dev` — days to keep dev entries (default: 7)
- [ ] `retention.prod` — days to keep prod aggregates (default: 90)
- [ ] `aggregation_intervals` — list of bucket sizes (default: `[1, 5, 60, 1440]` minutes)
- [ ] `sampling_rate` — float 0.0-1.0 for prod mode sampling (default: 1.0)
- [ ] `enabled` — global on/off toggle (default: true)
- [ ] `watchers` — map of watcher class to enabled/disabled + per-watcher config
- [ ] `recorders` — map of recorder class to enabled/disabled + per-recorder config
- [ ] Publish config file via `php lattice vendor:publish --tag=nightwatch-config`

### NightwatchServiceProvider
- [ ] Create `NightwatchServiceProvider`
- [ ] Register `StorageManager` as singleton in the container
- [ ] Register `RetentionManager` as singleton
- [ ] Register `Aggregator` as singleton
- [ ] Register `TimePartitioner` as singleton
- [ ] Conditionally register dev-mode watchers or prod-mode recorders based on resolved mode
- [ ] Boot watchers/recorders (hook into framework events)
- [ ] Register config file for publishing
- [ ] Register CLI commands
- [ ] Register web routes (if web interface is enabled)
- [ ] Provide `Nightwatch` facade with `record()`, `query()`, `prune()`, `toggle()`, `isEnabled()`

---

## Phase 2 — Data Collection (Watchers & Recorders)

### Base Classes
- [ ] Create abstract `Watcher` base class with `enabled`, `shouldRecord(entry)` filter, `record(entry)` method
- [ ] Create abstract `Recorder` base class with `enabled`, `sample()` probabilistic check, `record(entry)` aggregation method
- [ ] Create `EntryType` enum: `request`, `query`, `exception`, `event`, `cache`, `job`, `mail`, `log`, `model`, `gate`
- [ ] Create `Entry` value object with `type`, `timestamp`, `uuid`, `data`, `tags`, `batchId`
- [ ] Create `BatchManager` to group entries by request/job lifecycle

### Dev Mode Watchers (Individual Entry Storage)

#### RequestWatcher
- [ ] Hook into HTTP kernel (before/after middleware)
- [ ] Capture: method, URI, route name, controller action, headers, IP, session ID
- [ ] Capture: response status code, response size, content type
- [ ] Capture: total duration (ms)
- [ ] Capture: authenticated user (ID, email) if available
- [ ] Capture: middleware list applied
- [ ] Redact sensitive headers (Authorization, Cookie) by default — configurable
- [ ] Configurable: ignore paths (e.g., `/nightwatch/*`, health checks)
- [ ] Store as `requests` type entry
- [ ] Tests: capture GET/POST, header redaction, path ignoring, duration accuracy

#### QueryWatcher
- [ ] Hook into database query event
- [ ] Capture: raw SQL, bindings, duration (ms)
- [ ] Capture: connection name
- [ ] Capture: caller file and line number (backtrace analysis)
- [ ] Flag slow queries based on configurable threshold (default: 100ms)
- [ ] Detect N+1 query patterns (same query repeated in loop)
- [ ] Capture: query type (SELECT, INSERT, UPDATE, DELETE)
- [ ] Configurable: ignore specific tables or query patterns
- [ ] Store as `queries` type entry
- [ ] Tests: SQL capture, binding interpolation, slow flag, N+1 detection, caller resolution

#### ExceptionWatcher
- [ ] Hook into exception handler
- [ ] Capture: exception class, message, code
- [ ] Capture: full stack trace (file, line, function per frame)
- [ ] Capture: request context (URI, method) if within HTTP lifecycle
- [ ] Capture: previous exception chain
- [ ] Capture: custom context from exception if it implements a context interface
- [ ] Configurable: ignore specific exception classes
- [ ] Store as `exceptions` type entry
- [ ] Tests: basic exception, nested exception, request context, ignore list

#### EventWatcher
- [ ] Hook into event dispatcher
- [ ] Capture: event class name, payload (serialized)
- [ ] Capture: list of listeners that will handle / handled the event
- [ ] Capture: whether event was broadcast
- [ ] Configurable: ignore specific event classes (e.g., framework internals)
- [ ] Store as `events` type entry
- [ ] Tests: event capture, listener list, broadcast flag, ignore list

#### CacheWatcher
- [ ] Hook into cache events (hit, miss, write, forget)
- [ ] Capture: operation type, key, TTL (on write), value size
- [ ] Capture: cache store name
- [ ] Capture: duration of operation
- [ ] Configurable: ignore specific key patterns
- [ ] Store as `cache` type entry
- [ ] Tests: hit/miss/write/forget capture, key filtering

#### JobWatcher
- [ ] Hook into queue job lifecycle events (dispatched, processing, processed, failed)
- [ ] Capture: job class, queue name, connection name
- [ ] Capture: payload (serialized, with size limit)
- [ ] Capture: status (queued, processing, completed, failed)
- [ ] Capture: duration (processing time)
- [ ] Capture: attempt number, max tries
- [ ] Capture: exception on failure
- [ ] Store as `jobs` type entry
- [ ] Tests: full lifecycle, failure with exception, retry tracking

#### MailWatcher
- [ ] Hook into mail sending event
- [ ] Capture: to, cc, bcc, subject, from
- [ ] Capture: mailable class name
- [ ] Capture: rendered HTML body (store separately for preview)
- [ ] Capture: attachments list (name, size — not content)
- [ ] Capture: whether mail was queued
- [ ] Store HTML preview in a parallel file: `mail/{Y}/{m}/{d}/{H}/{uuid}.html.gz`
- [ ] Store metadata as `mail` type entry
- [ ] Tests: mail metadata, HTML preview storage and retrieval, queued flag

#### LogWatcher
- [ ] Hook into log handler
- [ ] Capture: level (emergency, alert, critical, error, warning, notice, info, debug)
- [ ] Capture: message, context array
- [ ] Capture: channel name
- [ ] Configurable: minimum level to record (default: debug in dev)
- [ ] Store as `logs` type entry
- [ ] Tests: level capture, context capture, minimum level filtering

#### ModelWatcher
- [ ] Hook into Eloquent model events (created, updated, deleted)
- [ ] Capture: model class, primary key
- [ ] Capture: operation type (created, updated, deleted)
- [ ] Capture: changed attributes with old and new values (on update)
- [ ] Capture: all attributes (on create)
- [ ] Redact sensitive attributes (password, secret) — configurable
- [ ] Configurable: watch specific models only, or exclude models
- [ ] Store as `models` type entry (uses general log storage path)
- [ ] Tests: create/update/delete capture, attribute diff, sensitive redaction

#### GateWatcher
- [ ] Hook into authorization gate checks
- [ ] Capture: ability name, result (allowed/denied)
- [ ] Capture: user ID and class
- [ ] Capture: arguments passed to gate
- [ ] Capture: policy class and method if applicable
- [ ] Store as `gates` type entry (uses general log storage path)
- [ ] Tests: allow/deny capture, policy resolution, argument capture

### Prod Mode Recorders (Aggregated Metric Storage)

#### RequestRecorder
- [ ] Hook into HTTP kernel (same hook point as RequestWatcher)
- [ ] Compute running percentiles: P99, P95, P50 for response time
- [ ] Track status code distribution (2xx, 3xx, 4xx, 5xx counts)
- [ ] Group by endpoint (route name or URI pattern)
- [ ] Track requests per minute
- [ ] Feed into Aggregator for bucketed storage
- [ ] Tests: percentile accuracy, status distribution, endpoint grouping

#### QueryRecorder
- [ ] Hook into database query event
- [ ] Normalize SQL (strip literal values) for grouping
- [ ] Track slow query frequency by normalized SQL
- [ ] Track average and P95 duration per normalized query
- [ ] Track total query count per interval
- [ ] Feed into Aggregator
- [ ] Tests: SQL normalization, frequency counting, duration tracking

#### ExceptionRecorder
- [ ] Hook into exception handler
- [ ] Count exceptions by class name
- [ ] Track trend (increasing/decreasing/stable) over rolling window
- [ ] Track first seen / last seen per exception class
- [ ] Feed into Aggregator
- [ ] Tests: counting, trend detection, first/last seen

#### CacheRecorder
- [ ] Hook into cache events
- [ ] Compute hit ratio: hits / (hits + misses) per interval
- [ ] Track total operations per interval
- [ ] Track cache store breakdown
- [ ] Feed into Aggregator
- [ ] Tests: ratio calculation, store breakdown

#### QueueRecorder
- [ ] Hook into queue job lifecycle
- [ ] Track throughput: jobs processed per interval
- [ ] Track average wait time (dispatched to processing)
- [ ] Track failure rate: failed / total
- [ ] Group by queue name
- [ ] Feed into Aggregator
- [ ] Tests: throughput, wait time, failure rate, queue grouping

#### ServerRecorder
- [ ] Probe CPU usage via CLI (`/proc/stat` on Linux, fallback for macOS/Windows)
- [ ] Probe memory usage via CLI (`/proc/meminfo` or `free`)
- [ ] Probe disk usage via `disk_free_space()` / `disk_total_space()`
- [ ] Run probes on a configurable interval (default: every 15 seconds)
- [ ] Feed into Aggregator
- [ ] Tests: probe parsing, fallback behavior, interval timing

### Mode Switching
- [ ] Implement auto-switch logic: resolve mode from `APP_ENV` (local/dev -> dev mode, production/staging -> prod mode)
- [ ] Allow manual override via `NIGHTWATCH_MODE` env var or config
- [ ] Ensure watchers and recorders are mutually exclusive (only one set active)
- [ ] Support runtime toggle without restart (re-register via container)
- [ ] Configurable sampling rate for prod mode (default: 1.0 = 100%, e.g., 0.1 = 10%)
- [ ] Tests: auto-switch logic, manual override, sampling rate application

---

## Phase 3 — API Layer

### NightwatchModule
- [ ] Create `NightwatchModule` extending the framework's Module class
- [ ] Register routes under configurable prefix (default: `/nightwatch`)
- [ ] Apply admin guard middleware (configurable: auth, IP whitelist, or custom)
- [ ] Register API routes for both dev and prod endpoints
- [ ] Include CORS headers for SPA consumption
- [ ] Tests: route registration, guard enforcement, CORS headers

### Dev Mode API Endpoints
- [ ] `GET /nightwatch/api/requests` — paginated request list with filtering (status, method, URI, duration)
- [ ] `GET /nightwatch/api/requests/{uuid}` — single request detail with related entries (queries, logs, etc.)
- [ ] `GET /nightwatch/api/queries` — paginated query list with filtering (slow, duration, SQL pattern)
- [ ] `GET /nightwatch/api/queries/{uuid}` — single query detail
- [ ] `GET /nightwatch/api/exceptions` — paginated exception list with filtering (class, message)
- [ ] `GET /nightwatch/api/exceptions/{uuid}` — single exception detail with stack trace
- [ ] `GET /nightwatch/api/events` — paginated event list
- [ ] `GET /nightwatch/api/events/{uuid}` — single event detail with listeners
- [ ] `GET /nightwatch/api/cache` — paginated cache operation list with filtering (hit/miss/write/forget)
- [ ] `GET /nightwatch/api/jobs` — paginated job list with filtering (status, queue)
- [ ] `GET /nightwatch/api/jobs/{uuid}` — single job detail
- [ ] `GET /nightwatch/api/mail` — paginated mail list
- [ ] `GET /nightwatch/api/mail/{uuid}` — mail detail
- [ ] `GET /nightwatch/api/mail/{uuid}/html` — rendered HTML preview (raw HTML response)
- [ ] `GET /nightwatch/api/logs` — paginated log list with filtering (level, channel, message)
- [ ] `GET /nightwatch/api/models` — paginated model change list with filtering (model class, operation)
- [ ] `GET /nightwatch/api/gates` — paginated gate check list with filtering (ability, result)
- [ ] `GET /nightwatch/api/batch/{batchId}` — all entries in a request/job batch (timeline view)
- [ ] All list endpoints support `from` and `to` query parameters for time range
- [ ] All list endpoints support `limit` and `offset` for pagination
- [ ] Tests: each endpoint with valid/invalid params, filtering, pagination, time range

### Prod Mode API Endpoints
- [ ] `GET /nightwatch/api/overview` — dashboard summary (all key metrics for current period)
- [ ] `GET /nightwatch/api/slow-requests` — top slow endpoints by P95 latency
- [ ] `GET /nightwatch/api/slow-queries` — top slow queries by frequency and duration
- [ ] `GET /nightwatch/api/exception-counts` — top exceptions by count with trend
- [ ] `GET /nightwatch/api/cache-ratio` — cache hit ratio time series
- [ ] `GET /nightwatch/api/queue-throughput` — queue throughput time series
- [ ] `GET /nightwatch/api/servers` — server vitals time series (CPU, memory, disk)
- [ ] All prod endpoints support `period` parameter (1h, 6h, 24h, 7d, 30d)
- [ ] All prod endpoints support `resolution` parameter (1min, 5min, 1hr, 1day — auto-selected based on period)
- [ ] Tests: each endpoint, period/resolution combinations, empty data handling

### Control Endpoints
- [ ] `POST /nightwatch/api/toggle` — enable/disable monitoring (persists to config/state file)
- [ ] `POST /nightwatch/api/prune` — trigger retention pruning, return summary
- [ ] `GET /nightwatch/api/status` — current mode, enabled state, storage size, entry counts
- [ ] Tests: toggle persistence, prune execution, status accuracy

---

## Phase 4 — Web SPA

### Shell & Navigation
- [ ] Create SPA entry point with framework asset serving
- [ ] Auto-detect current mode (dev/prod) from API status endpoint
- [ ] Top navigation bar: mode indicator, time range picker, refresh button, toggle switch
- [ ] Sidebar navigation for entry types (dev) or metric categories (prod)
- [ ] Dark/light theme toggle with system preference detection
- [ ] Responsive layout (desktop sidebar, mobile bottom nav or hamburger)

### Dev View — Request List
- [ ] Table: method, URI, status, duration, timestamp
- [ ] Color-coded status badges (green 2xx, yellow 3xx, red 4xx/5xx)
- [ ] Duration highlighting (green < 200ms, yellow < 1s, red > 1s)
- [ ] Clickable rows open detail panel
- [ ] Detail panel: full headers, payload, response preview, related queries/logs/exceptions
- [ ] Filter bar: method, status range, URI search, min/max duration
- [ ] Auto-refresh toggle

### Dev View — Query List
- [ ] Table: SQL (truncated), duration, connection, slow flag, timestamp
- [ ] Slow query highlighting (red background or badge)
- [ ] N+1 detection badge
- [ ] Clickable rows open detail panel
- [ ] Detail panel: full SQL with syntax highlighting, bindings, caller file:line, explain plan link
- [ ] Filter bar: slow only, duration threshold, SQL search, connection

### Dev View — Exception List
- [ ] Table: exception class, message (truncated), count, last occurrence
- [ ] Grouped by exception class with occurrence count
- [ ] Clickable rows open detail panel
- [ ] Detail panel: full message, stack trace with code context, request info, previous exceptions
- [ ] Stack trace: collapsible frames, vendor frame dimming, app frame highlighting
- [ ] Filter bar: class search, message search

### Dev View — Event List
- [ ] Table: event class, listener count, broadcast flag, timestamp
- [ ] Clickable rows open detail panel
- [ ] Detail panel: event payload, listener list with execution order
- [ ] Filter bar: event class search

### Dev View — Cache Stats
- [ ] Table: operation, key, store, duration, timestamp
- [ ] Color-coded operation badges (green hit, red miss, blue write, gray forget)
- [ ] Summary stats bar: hit ratio, total ops
- [ ] Filter bar: operation type, key search, store

### Dev View — Job List
- [ ] Table: job class, queue, status, duration, timestamp
- [ ] Color-coded status badges (gray queued, blue processing, green completed, red failed)
- [ ] Clickable rows open detail panel
- [ ] Detail panel: payload, exception (if failed), attempts, related entries
- [ ] Filter bar: status, queue, job class search

### Dev View — Mail Previewer
- [ ] Table: to, subject, mailable class, queued flag, timestamp
- [ ] Clickable rows open detail panel
- [ ] Detail panel: full headers, attachment list
- [ ] HTML preview rendered in sandboxed iframe
- [ ] Toggle between HTML preview and raw HTML source

### Dev View — Log Viewer
- [ ] Table: level, message (truncated), channel, timestamp
- [ ] Color-coded level badges (red error+, yellow warning, blue info, gray debug)
- [ ] Clickable rows expand to show full message and context
- [ ] Filter bar: level selector, channel, message search
- [ ] Live tail mode (auto-scroll to newest)

### Dev View — Model Changes
- [ ] Table: model class, key, operation, timestamp
- [ ] Clickable rows open detail panel
- [ ] Detail panel: attribute diff (old vs new values, highlighted changes)
- [ ] Filter bar: model class, operation type

### Dev View — Gate Checks
- [ ] Table: ability, result (allowed/denied), user, timestamp
- [ ] Color-coded result badges
- [ ] Detail panel: arguments, policy info
- [ ] Filter bar: ability, result

### Dev View — Batch/Timeline View
- [ ] Visual timeline of all entries in a single request or job lifecycle
- [ ] Ordered by timestamp within the batch
- [ ] Color-coded by entry type
- [ ] Duration bars showing relative timing
- [ ] Click entry to expand detail

### Prod View — Overview Dashboard
- [ ] Card grid layout with key metrics
- [ ] Each card: title, current value, trend indicator (up/down/stable), sparkline
- [ ] Cards: requests/min, avg response time, P99 latency, error rate, slow queries, cache hit ratio, queue throughput, CPU, memory, disk
- [ ] Time range picker (1h, 6h, 24h, 7d, 30d)
- [ ] Auto-refresh on configurable interval

### Prod View — Slow Requests
- [ ] Table: endpoint, P95, P99, avg, request count
- [ ] Sparkline per endpoint showing latency trend
- [ ] Click to drill into time-series chart for that endpoint

### Prod View — Slow Queries
- [ ] Table: normalized SQL, avg duration, P95, frequency, last seen
- [ ] SQL syntax highlighting
- [ ] Sorted by frequency or duration (toggleable)

### Prod View — Exceptions
- [ ] Table: exception class, count, trend, first seen, last seen
- [ ] Trend indicator (increasing/decreasing/stable)
- [ ] Click to see occurrence chart

### Prod View — Cache
- [ ] Line chart: hit ratio over time
- [ ] Breakdown by cache store
- [ ] Current ratio prominently displayed

### Prod View — Queue
- [ ] Line chart: throughput over time
- [ ] Table: queue name, processed, failed, failure rate, avg wait time
- [ ] Failure rate highlighting

### Prod View — Server Vitals
- [ ] Gauge charts for CPU, memory, disk (current values)
- [ ] Line charts for historical trends
- [ ] Alert thresholds (configurable, visual indicators when exceeded)

---

## Phase 5 — CLI Terminal UI (TUI)

### Interactive TUI Entry Point
- [ ] `php lattice nightwatch` — launch interactive TUI
- [ ] Auto-detect dev/prod mode and show appropriate interface
- [ ] Full-screen terminal application using a PHP TUI library (e.g., `php-tui/php-tui` or custom)
- [ ] Keyboard-driven navigation (arrow keys, tab, enter, escape)
- [ ] Graceful terminal resize handling
- [ ] Clean exit on Ctrl+C / q

### Dev Mode TUI
- [ ] Tab bar for entry types (requests, queries, exceptions, events, cache, jobs, mail, logs)
- [ ] Scrollable entry list per tab with column-aligned display
- [ ] Detail panel (split pane or overlay) on Enter
- [ ] Slow query highlighting with color
- [ ] Stack trace viewer with scrollable frames
- [ ] Log viewer with level-based coloring (red/yellow/blue/gray)
- [ ] Search/filter within current list
- [ ] Keyboard shortcuts: `r` requests, `q` queries, `e` exceptions, `j` jobs, `l` logs, `?` help

### Prod Mode TUI
- [ ] Metrics cards with current values and trend arrows
- [ ] Sparkline charts using Unicode block characters
- [ ] CPU/memory/disk gauges (horizontal bar charts, htop-style)
- [ ] P99/P95/P50 latency table
- [ ] Slow query table
- [ ] Top exceptions table
- [ ] Queue throughput summary
- [ ] Auto-refresh on configurable interval (default: 5 seconds)

### Live Tail Mode
- [ ] `php lattice nightwatch --tail` — stream new entries as they are written
- [ ] Color-coded output by entry type
- [ ] Compact single-line format per entry
- [ ] Filter by type: `--tail --type=queries`
- [ ] Filter by level: `--tail --level=error` (for logs)
- [ ] Ctrl+C to stop

### Non-Interactive CLI Commands

#### nightwatch:requests
- [ ] `php lattice nightwatch:requests` — list recent requests in table format
- [ ] `--limit=N` — number of entries (default: 50)
- [ ] `--status=4xx` — filter by status code range
- [ ] `--slow` — only requests above duration threshold
- [ ] `--from` / `--to` — time range
- [ ] `--json` — output as JSON for piping

#### nightwatch:queries
- [ ] `php lattice nightwatch:queries` — list recent queries
- [ ] `--slow` — only queries above slow threshold
- [ ] `--limit=N`
- [ ] `--sort=duration|count` — sort order
- [ ] `--json`

#### nightwatch:exceptions
- [ ] `php lattice nightwatch:exceptions` — list recent exceptions
- [ ] `--limit=N`
- [ ] `--class=ClassName` — filter by exception class
- [ ] `--json`

#### nightwatch:logs
- [ ] `php lattice nightwatch:logs` — list recent log entries
- [ ] `--level=error` — minimum level filter
- [ ] `--channel=name` — filter by channel
- [ ] `--limit=N`
- [ ] `--json`

#### nightwatch:metrics
- [ ] `php lattice nightwatch:metrics` — display prod metrics summary
- [ ] `--period=24h` — time period
- [ ] `--json`

#### nightwatch:servers
- [ ] `php lattice nightwatch:servers` — display current server vitals
- [ ] `--watch` — refresh on interval (like `watch` command)
- [ ] `--json`

#### nightwatch:prune
- [ ] `php lattice nightwatch:prune` — run retention pruning
- [ ] `--type=queries` — prune specific type only
- [ ] `--before=2026-03-01` — prune before specific date
- [ ] `--dry-run` — show what would be deleted without deleting
- [ ] `--force` — skip confirmation prompt
- [ ] Output: count of deleted directories, freed space

#### nightwatch:toggle
- [ ] `php lattice nightwatch:toggle` — toggle monitoring on/off
- [ ] `php lattice nightwatch:toggle --on` — explicitly enable
- [ ] `php lattice nightwatch:toggle --off` — explicitly disable
- [ ] Output: current state after toggle

### Compact Mode
- [ ] `php lattice nightwatch --compact` — reduced-height TUI for smaller terminals
- [ ] Fewer rows, abbreviated columns, no detail panel (open in pager instead)

---

## Phase 6 — Polish & Production Readiness

### Environment-Based Auto-Mode
- [ ] Verify dev mode activates for `APP_ENV=local` and `APP_ENV=dev`
- [ ] Verify prod mode activates for `APP_ENV=production` and `APP_ENV=staging`
- [ ] Verify `NIGHTWATCH_MODE=dev` override works in any environment
- [ ] Verify `NIGHTWATCH_MODE=prod` override works in any environment
- [ ] Integration tests for mode resolution

### Access Control
- [ ] IP allow-list for web dashboard access (configurable, default: `127.0.0.1`)
- [ ] Authenticated user allow-list (specific user IDs or emails)
- [ ] Custom authorization gate (closure-based)
- [ ] Combination: IP AND user checks
- [ ] Return 403 for unauthorized access
- [ ] CLI commands bypass web access control (they run locally)
- [ ] Tests: IP check, user check, gate check, 403 response

### Retention Configuration
- [ ] Default: 7 days for dev mode, 90 days for prod mode
- [ ] Per-type retention override (e.g., keep exceptions longer)
- [ ] Scheduled pruning command registration (daily cron)
- [ ] Storage size reporting (`nightwatch:status` command showing disk usage per type)
- [ ] Tests: per-type retention, scheduled prune, size reporting

### Performance Optimization
- [ ] Benchmark overhead of watchers on typical request (target: < 5ms added latency)
- [ ] Implement sampling for prod mode (configurable rate)
- [ ] Buffer writes and flush at end of request (single I/O operation per type)
- [ ] Avoid recording Nightwatch's own requests/queries
- [ ] Lazy serialization — defer JSON encoding until flush
- [ ] Profile memory usage of watchers during request lifecycle
- [ ] Connection-less: ensure no database connections are opened by Nightwatch
- [ ] Tests: overhead benchmark, sampling rate verification, self-exclusion

### Documentation
- [ ] README with installation and quick start
- [ ] Configuration reference (all options documented)
- [ ] Storage architecture explanation
- [ ] Dev mode usage guide (common debugging workflows)
- [ ] Prod mode usage guide (monitoring setup, alerting integration)
- [ ] CLI command reference
- [ ] Customization guide (custom watchers, custom recorders)
- [ ] Troubleshooting (common issues, storage growth, permissions)

### Testing

#### Storage Engine Tests
- [ ] NdjsonWriter: write, batch write, compression, concurrent safety
- [ ] NdjsonReader: read, decompress, filter, paginate, corrupted lines
- [ ] TimePartitioner: path resolution, ranges, edge cases
- [ ] StorageManager: round-trip, queries, auto-creation
- [ ] RetentionManager: pruning, TTL, concurrent safety
- [ ] Aggregator: percentiles, incremental, rollup

#### Watcher Tests
- [ ] RequestWatcher: all HTTP methods, header redaction, path ignoring
- [ ] QueryWatcher: SQL capture, slow detection, N+1 detection
- [ ] ExceptionWatcher: basic, nested, context
- [ ] EventWatcher: capture, listeners, ignore list
- [ ] CacheWatcher: all operations
- [ ] JobWatcher: full lifecycle, failure
- [ ] MailWatcher: metadata, HTML preview
- [ ] LogWatcher: levels, context, minimum level
- [ ] ModelWatcher: CRUD, attribute diff, redaction
- [ ] GateWatcher: allow/deny, policy

#### Recorder Tests
- [ ] RequestRecorder: percentiles, status distribution
- [ ] QueryRecorder: SQL normalization, frequency
- [ ] ExceptionRecorder: counting, trends
- [ ] CacheRecorder: hit ratio
- [ ] QueueRecorder: throughput, failure rate
- [ ] ServerRecorder: probe parsing, fallback

#### API Tests
- [ ] All dev endpoints: list, detail, filter, pagination
- [ ] All prod endpoints: overview, time series, period/resolution
- [ ] Control endpoints: toggle, prune, status
- [ ] Authorization: IP check, user check, 403

#### CLI Tests
- [ ] Each non-interactive command with all flag combinations
- [ ] Output format (table, JSON)
- [ ] Prune dry-run vs actual
- [ ] Toggle state persistence

#### E2E Tests
- [ ] Full request lifecycle: make HTTP request -> verify entries stored -> query via API -> display in CLI
- [ ] Mode switching: change APP_ENV -> verify correct watchers/recorders activate
- [ ] Retention: create old data -> run prune -> verify deletion
- [ ] Aggregation: store raw entries -> run aggregation -> verify bucketed output
