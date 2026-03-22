# Lattice Loom -- Task List

## Phase 1: Metrics Collection

### Job Lifecycle Event Listeners
- [ ] Define `JobDispatched` event class with job ID, class name, queue, tags, timestamp
- [ ] Define `JobProcessing` event class with job ID, worker ID, queue, timestamp
- [ ] Define `JobProcessed` event class with job ID, queue, runtime (ms), timestamp
- [ ] Define `JobFailed` event class with job ID, queue, exception class, message, trace, attempt count, timestamp
- [ ] Define `JobReleased` event class with job ID, queue, delay, attempt count, timestamp
- [ ] Create `MetricsCollector` listener that subscribes to all job lifecycle events
- [ ] Instrument `Dispatcher::dispatch()` to fire `JobDispatched` event via `lattice/events`
- [ ] Instrument `Dispatcher::dispatchAfter()` to fire `JobDispatched` event with delay metadata
- [ ] Instrument `Worker::processJob()` to fire `JobProcessing` before and `JobProcessed`/`JobFailed` after
- [ ] Ensure all events carry enough context for metrics (queue name, job class, tags, timing)
- [ ] Add tag extraction from job class (support a `HasTags` interface that jobs can implement)
- [ ] Unit tests for each event class construction and serialization

### Metrics Storage in Redis Sorted Sets
- [ ] Define `MetricsWriterInterface` with methods: `recordJobProcessed()`, `recordJobFailed()`, `recordJobDispatched()`, `recordWaitTime()`, `recordRuntime()`, `incrementThroughput()`
- [ ] Define `MetricsRepositoryInterface` with methods: `getThroughput()`, `getRuntime()`, `getWaitTime()`, `getFailureRate()`, `getQueueSizes()`, `getRecentJobs()`, `getSnapshot()`
- [ ] Implement `RedisMetricsStore` using `lattice/cache` `RedisCacheDriver`
- [ ] Use Redis sorted sets for throughput time-series: key `loom:throughput:{queue}:{minute_bucket}`, score = timestamp, member = count
- [ ] Use Redis sorted sets for runtime time-series: key `loom:runtime:{queue}:{minute_bucket}`, score = timestamp, member = runtime_ms
- [ ] Use Redis sorted sets for wait-time time-series: key `loom:wait:{queue}:{minute_bucket}`, score = timestamp, member = wait_ms
- [ ] Use Redis hash for aggregate counters: `loom:stats` with fields `processed`, `failed`, `total_runtime`, `total_wait`
- [ ] Use Redis list for recent job snapshots: `loom:recent:{queue}` capped at configurable length
- [ ] Use Redis hash per worker: `loom:workers:{worker_id}` with fields for queue, pid, memory, last_heartbeat, jobs_processed
- [ ] Implement `DatabaseMetricsStore` as fallback for environments without Redis
- [ ] Create database migration for `loom_metrics` table (timestamp, queue, metric_type, value)
- [ ] Create database migration for `loom_recent_jobs` table (job_id, queue, class, status, runtime, created_at)
- [ ] Create database migration for `loom_workers` table (worker_id, queue, pid, memory, last_heartbeat)
- [ ] Unit tests for `RedisMetricsStore` read/write cycle using `FakeRedisDriver`
- [ ] Unit tests for `DatabaseMetricsStore` using in-memory SQLite

### Failed Job Storage
- [ ] Integrate with existing `FailedJobStoreInterface` from `lattice/queue` -- no duplication
- [ ] Store full job payload alongside failure record for inspection
- [ ] Store exception class, message, file, line, and full stack trace as structured data
- [ ] Enrich failed job data with Loom-specific metadata (tags, runtime before failure, worker ID)
- [ ] Index failed jobs by queue and by tag for filtered retrieval
- [ ] Add `getFailedByQueue(string $queue): array` to `MetricsRepositoryInterface`
- [ ] Add `getFailedByTag(string $tag): array` to `MetricsRepositoryInterface`
- [ ] Unit tests for enriched failed job storage and retrieval

### Queue Size Polling and Tracking
- [ ] Implement periodic queue size snapshots using `QueueDriverInterface::size()`
- [ ] Store queue size time-series in Redis sorted set: `loom:queue_size:{queue}:{minute_bucket}`
- [ ] Create a `QueueSizeRecorder` that runs on a timer (configurable interval, default 60s)
- [ ] Support tracking multiple queues from configuration
- [ ] Auto-discover active queues from metrics data (queues that have had jobs dispatched)
- [ ] Unit tests for queue size recording and time-series retrieval

### Worker Heartbeat Tracking
- [ ] Workers send heartbeat every N seconds (configurable, default 15s) via `WorkerHeartbeat` event
- [ ] Define `WorkerStarted` event class with worker ID, queue, PID, timestamp
- [ ] Define `WorkerStopped` event class with worker ID, queue, reason, timestamp
- [ ] Define `WorkerHeartbeat` event class with worker ID, queue, memory usage, jobs processed count, timestamp
- [ ] Create `WorkerMonitor` listener that subscribes to worker events and heartbeats
- [ ] Heartbeat includes: worker ID, assigned queue, PID, memory usage (MB), uptime, jobs processed since start
- [ ] `WorkerMonitor` listener updates Redis hash `loom:workers:{id}` on each heartbeat
- [ ] Register worker on start, update on heartbeat, unregister on stop
- [ ] Detect stale workers: if last heartbeat > 2x heartbeat interval, mark as `inactive`
- [ ] Remove worker entry after configurable timeout (default 5 minutes of no heartbeat)
- [ ] Generate unique worker ID on worker start (hostname + PID + random suffix)
- [ ] Unit tests for heartbeat recording, staleness detection, and cleanup

### Retention Policy and Cleanup
- [ ] Implement `MetricsPruner` that removes data older than configured retention period
- [ ] Prune throughput sorted sets: remove members with score < (now - retention)
- [ ] Prune runtime sorted sets: remove members with score < (now - retention)
- [ ] Prune wait-time sorted sets: remove members with score < (now - retention)
- [ ] Prune queue-size sorted sets: remove members with score < (now - retention)
- [ ] Trim recent jobs lists to max configured length (default 10,000)
- [ ] Clean up stale worker hashes
- [ ] Run pruner on a configurable interval (default every 5 minutes)
- [ ] Support running pruner as a CLI command: `php lattice loom:purge`
- [ ] Unit tests for pruner with time manipulation

### LoomServiceProvider with Auto-Registration
- [ ] Create `LoomServiceProvider` implementing provider interface
- [ ] Register `LoomConfig` from `config/loom.php` (with sensible defaults)
- [ ] Bind `MetricsWriterInterface` and `MetricsRepositoryInterface` to configured implementation (Redis or database)
- [ ] Register `MetricsCollector` as event listener for all job lifecycle events
- [ ] Register `WorkerMonitor` as event listener for worker events
- [ ] Register `MetricsPruner` with configured retention and interval
- [ ] Register `QueueSizeRecorder` with configured interval
- [ ] Auto-register all event listeners and services without manual wiring
- [ ] Conditionally register alerting services if `alerting.enabled` is true
- [ ] Unit tests for service provider registration and configuration binding


## Phase 2: API Layer

### GET /api/loom/stats -- Dashboard Overview
- [ ] Return JSON with: jobs processed (last hour), jobs failed (last hour), total processed, total failed, current throughput (jobs/min), average runtime (ms), average wait time (ms), active workers count, queue sizes summary
- [ ] Support `?period=1h|6h|24h|7d` query parameter for time window
- [ ] Cache response for 5 seconds to avoid Redis pressure on rapid refreshes
- [ ] Unit test for stats endpoint response structure
- [ ] Unit test for period parameter handling

### GET /api/loom/jobs/recent -- Recently Processed Jobs
- [ ] Paginated list of recently processed jobs (default 25 per page)
- [ ] Support query parameters: `?page=1&per_page=25&queue=default&search=ClassName`
- [ ] Each entry includes: id, class name, queue, status, attempts, runtime (ms), created_at, completed_at
- [ ] Sort by most recent first (descending timestamp)
- [ ] Unit tests for pagination
- [ ] Unit tests for queue and search filtering

### GET /api/loom/jobs/failed -- Failed Jobs
- [ ] Paginated list of failed jobs
- [ ] Support query parameters: `?page=1&per_page=25&queue=default&search=ClassName`
- [ ] Each entry includes: id, class name, queue, exception class, exception message, attempts, failed_at
- [ ] Support text search across job class name, exception class, and exception message
- [ ] Unit tests for pagination, search, and filtering

### GET /api/loom/jobs/pending -- Pending Jobs by Queue
- [ ] Paginated list of jobs currently awaiting processing
- [ ] Group or filter by queue name
- [ ] Each entry includes: id, class name, queue, created_at, available_at
- [ ] Show estimated wait time based on current throughput
- [ ] Unit tests for pending jobs listing and queue filtering

### GET /api/loom/jobs/:id -- Job Detail
- [ ] Return full job detail: id, class, queue, connection, status, payload (deserialized JSON), attempts, max_attempts, timeout, created_at, available_at, started_at, completed_at, runtime_ms
- [ ] For failed jobs additionally include: exception class, exception message, exception trace (array of frames), failed_at
- [ ] For pending jobs include: position in queue (if available), estimated wait time
- [ ] Payload displayed as pretty-printed JSON of the serialized job properties
- [ ] Unit test for job detail response with completed job
- [ ] Unit test for job detail response with failed job
- [ ] Unit test for 404 when job not found

### POST /api/loom/jobs/:id/retry -- Retry a Single Failed Job
- [ ] Delegates to `FailedJobStoreInterface::retry()`
- [ ] Return 200 with `{ "status": "retried", "job_id": "..." }` on success
- [ ] Return 404 if job ID not found in failed store
- [ ] Unit tests for successful retry and 404 handling

### POST /api/loom/jobs/retry-all -- Retry All Failed Jobs
- [ ] Iterate all failed jobs and retry each one
- [ ] Return 200 with `{ "status": "retried", "count": N }`
- [ ] Unit tests for retry-all with multiple failed jobs

### DELETE /api/loom/jobs/:id -- Delete a Failed Job
- [ ] Delegates to `FailedJobStoreInterface::delete()`
- [ ] Return 200 with `{ "status": "deleted" }` on success
- [ ] Return 404 if job ID not found
- [ ] Unit tests for delete and 404 handling

### GET /api/loom/metrics/throughput -- Jobs Per Minute Over Time
- [ ] Return time-series array of `{ timestamp, value }` for throughput
- [ ] Support `?period=5m|1h|24h|7d` and `?granularity=minute|hour` query parameters
- [ ] Support filtering by queue name
- [ ] Unit tests for time-series response shape and parameters

### GET /api/loom/metrics/runtime -- Average Runtime Over Time
- [ ] Return time-series array of `{ timestamp, value }` for average runtime
- [ ] Support `?period=5m|1h|24h|7d` and `?granularity=minute|hour` query parameters
- [ ] Support p50/p95/p99 percentile breakdowns
- [ ] Unit tests for runtime metrics response

### GET /api/loom/workers -- Active Workers List
- [ ] List all workers with: id, queue, status (active/inactive), pid, memory_mb, uptime_seconds, jobs_processed, last_heartbeat
- [ ] Filter stale workers: include but mark as `inactive` if heartbeat is overdue
- [ ] Sort by status (active first), then by last heartbeat descending
- [ ] Unit test for worker list with mix of active and stale workers
- [ ] Unit test for empty worker list

### SSE /api/loom/events -- Real-Time Job Events Stream
- [ ] Set headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`
- [ ] Event types: `job.processed`, `job.failed`, `metrics.snapshot`, `worker.status`, `queue.size`
- [ ] `job.processed` event data: `{ "id", "class", "queue", "runtime_ms" }`
- [ ] `job.failed` event data: `{ "id", "class", "queue", "exception", "message" }`
- [ ] `metrics.snapshot` event data: `{ "throughput", "failure_rate", "avg_runtime", "active_workers" }` -- sent every N seconds
- [ ] `worker.status` event data: `{ "worker_id", "status", "queue", "memory_mb" }` -- on heartbeat
- [ ] `queue.size` event data: `{ "queue", "size" }` -- on size change
- [ ] Send heartbeat comment (`: heartbeat\n\n`) every 15 seconds to keep connection alive
- [ ] Support `Last-Event-ID` header for reconnection -- resume from missed events
- [ ] Use Redis pub/sub channel `loom:events` for cross-process event distribution
- [ ] Fallback to polling metrics store if Redis pub/sub unavailable
- [ ] Integration test for SSE connection and event reception
- [ ] Unit test for event serialization format

### LoomModule with Admin Guard
- [ ] Create `LoomModule` with `#[Module]` attribute
- [ ] Register all controllers: `DashboardController`, `JobsController`, `QueuesController`, `WorkersController`, `MetricsController`, `EventStreamController`
- [ ] Register `LoomServiceProvider` as provider
- [ ] Create `LoomAdminGuard` middleware that checks authorization
- [ ] Guard supports multiple strategies: callback, gate/policy, role check
- [ ] Default guard: deny all in production, allow all in development
- [ ] Configurable via `loom.middleware` config key
- [ ] Route prefix configurable via `loom.prefix` (default `/loom`)
- [ ] Static asset serving for frontend SPA (serve `resources/dist/` files)
- [ ] Catch-all route for SPA client-side routing (`/loom/{any}` returns `index.html`)
- [ ] Unit test for module registration
- [ ] Unit test for admin guard rejection and acceptance
- [ ] Unit test for route prefix configuration


## Phase 3: Frontend SPA

### Project Setup (Vite + React + TailwindCSS)
- [ ] Initialize frontend build with Vite + React
- [ ] Configure TailwindCSS for styling
- [ ] Configure build output to `resources/dist/`
- [ ] Create `App` root component with React Router
- [ ] Define API client module with base URL from `<meta>` tag or config
- [ ] Create SSE connection manager hook with auto-reconnect
- [ ] Create shared data fetching hook with loading/error states and auto-refresh

### Layout Shell (Sidebar Nav, Header with Stats)
- [ ] Create shared layout: sidebar navigation with links to all pages
- [ ] Header with connection status indicator (connected/reconnecting/disconnected)
- [ ] Header with key stats summary (throughput, failed count, active workers)
- [ ] Collapsible sidebar for mobile/tablet viewports
- [ ] Active page indicator in sidebar navigation

### Dashboard Page (Key Metrics Cards, Throughput Chart, Queue Sizes)
- [ ] Key metrics cards row: total processed, total failed, throughput/min, avg runtime, avg wait, active workers
- [ ] Throughput chart: line chart showing jobs processed per minute over selected period
- [ ] Failure rate chart: line chart showing failure percentage over time
- [ ] Queue depth chart: stacked area chart showing pending jobs per queue
- [ ] Period selector: 5m, 1h, 24h, 7d toggle
- [ ] Auto-update metrics cards via SSE `metrics.snapshot` events
- [ ] Loading skeleton states while data is being fetched
- [ ] Error state with retry button if API is unreachable

### Recent Jobs Page (Sortable Table, Auto-Refresh)
- [ ] Jobs table with columns: status icon, job class, queue, runtime, attempts, timestamp
- [ ] Click row to navigate to job detail page
- [ ] Sort by column (timestamp, runtime, attempts)
- [ ] Pagination controls (previous/next, page size selector)
- [ ] Filter bar: queue dropdown, search text field
- [ ] Auto-refresh at configurable interval (5s, 15s, 30s, 60s, off)
- [ ] Real-time new job indicator via SSE (badge showing "N new jobs" with click to refresh)
- [ ] Empty state with helpful message

### Failed Jobs Page (Table, Retry/Delete Buttons, Bulk Actions)
- [ ] Failed jobs table with columns: status icon, job class, queue, exception, attempts, failed_at
- [ ] Individual row actions: retry button and delete button with confirmation
- [ ] Bulk actions toolbar: "Retry All" and "Delete All" with confirmation dialog
- [ ] Search bar for filtering by class name or exception message
- [ ] Pagination controls
- [ ] Success/error feedback toast on retry and delete actions
- [ ] Empty state with helpful message

### Pending Jobs Page (Per-Queue Breakdown)
- [ ] Per-queue sections showing pending job count and list
- [ ] Each entry shows: job class, created_at, estimated wait time
- [ ] Queue depth bar chart or summary cards
- [ ] Auto-refresh to track queue drain progress

### Job Detail Page (Payload JSON Viewer, Exception with Stack Trace, Retry)
- [ ] Header: job class name, status badge (completed/failed/pending), queue badge
- [ ] Metadata section: ID, connection, attempts/max, timeout, timestamps, runtime
- [ ] Payload viewer: syntax-highlighted JSON tree of the serialized job properties
- [ ] Collapsible sections for large payloads
- [ ] For failed jobs: exception panel with class, message, and full stack trace
- [ ] Stack trace with file paths and line numbers
- [ ] Retry button (failed jobs only) with loading state and success/error feedback
- [ ] Delete button (failed jobs only) with confirmation dialog
- [ ] "Back to list" navigation link
- [ ] Copy job ID / Copy payload buttons

### Metrics Page (Throughput Chart, Runtime Chart, Configurable Time Range)
- [ ] Throughput over time: line chart with queue selector (all queues or specific queue)
- [ ] Average runtime over time: line chart with p50/p95/p99 lines
- [ ] Wait time over time: line chart per queue
- [ ] Failure rate over time: line chart with threshold reference line
- [ ] Queue depth over time: stacked area chart
- [ ] Configurable time range selector: 5m, 1h, 6h, 24h, 7d
- [ ] Chart library: lightweight option (Chart.js, uPlot, or custom SVG)
- [ ] Responsive charts that resize with container
- [ ] Tooltip on hover showing exact values and timestamp

### Workers Page (Active Workers, Last Heartbeat, Memory Usage)
- [ ] Worker status table: ID, queue, status badge (active/inactive/stale), PID, memory, uptime, jobs processed, last heartbeat
- [ ] Color-coded status badges: green (active), gray (inactive), red (stale/dead)
- [ ] Time-since-heartbeat display that updates in real-time
- [ ] SSE-driven updates for worker status changes
- [ ] Empty state when no workers are running

### Real-Time SSE Integration for Live Updates
- [ ] SSE connection manager: connect, auto-reconnect with exponential backoff, max retries
- [ ] Connection status indicator in header: green dot (connected), yellow (reconnecting), red (disconnected)
- [ ] Route SSE events to appropriate page state
- [ ] Update dashboard metric cards on `metrics.snapshot` events
- [ ] Append new jobs to jobs list on `job.processed` and `job.failed` events
- [ ] Update worker table on `worker.status` events
- [ ] Update queue depth displays on `queue.size` events
- [ ] Debounce rapid updates to avoid excessive re-renders (batch updates every 500ms)
- [ ] Pause SSE when browser tab is hidden, resume on visibility change

### Responsive Design, Dark/Light Mode
- [ ] CSS custom properties (variables) for all colors
- [ ] Light theme (default): clean white background, subtle borders, dark text
- [ ] Dark theme: dark gray background, lighter text, adjusted chart colors
- [ ] Theme toggle button in header
- [ ] Persist theme preference in localStorage
- [ ] Respect `prefers-color-scheme` media query for initial theme
- [ ] Ensure all charts are legible in both themes
- [ ] Ensure all status badges and color indicators have sufficient contrast in both themes
- [ ] Mobile-responsive layouts: stack cards vertically, collapsible sidebar, touch-friendly buttons
- [ ] Tablet breakpoint with adapted table widths


## Phase 4: Polish

### Failure Alerting (Configurable Threshold, Notification Channel)
- [ ] `AlertManager` that evaluates threshold rules on each metrics snapshot
- [ ] `ThresholdChecker` with configurable rules: failure rate > X%, queue depth > N, worker count < N, avg runtime > Xms
- [ ] Alert channels: log (default), webhook (POST to URL), email (via lattice notifications if available)
- [ ] Alert cooldown: don't re-alert for the same condition within N minutes
- [ ] Alert history: store last N alerts in Redis for display in dashboard
- [ ] Dashboard alert banner: show active alerts at top of page
- [ ] Alert configuration via `config/loom.php` `alerting` section
- [ ] Unit tests for threshold evaluation logic
- [ ] Unit tests for cooldown behavior
- [ ] Integration test for webhook alert delivery

### Queue Balancing Recommendations
- [ ] Analyze queue metrics to detect imbalances: one queue growing while others are idle
- [ ] Suggest worker redistribution: "Queue 'emails' has 500 pending jobs. Consider adding 2 workers."
- [ ] Display recommendations on monitoring page as advisory cards
- [ ] Base recommendations on: queue depth trend, worker count per queue, throughput capacity
- [ ] Recommendations are informational only (Loom does not manage workers)
- [ ] Unit tests for recommendation engine with various queue scenarios

### Export Failed Jobs (CSV)
- [ ] Export all failed jobs as CSV download
- [ ] CSV columns: id, class, queue, exception_class, exception_message, attempts, failed_at, payload_summary
- [ ] Support filtering exported jobs by queue or date range
- [ ] Frontend download button on failed jobs page
- [ ] Unit test for CSV generation format

### Documentation
- [ ] README.md with installation, configuration, and quick start guide
- [ ] Document all configuration options with defaults and descriptions
- [ ] Document API endpoints with request/response examples
- [ ] Document event classes and how to listen for Loom events in user code
- [ ] Document how to customize the admin guard for production
- [ ] Document metrics storage drivers and when to use each
- [ ] Document SSE endpoint and how to consume it from custom clients
- [ ] Document tag system and how to implement `HasTags` on job classes
- [ ] Document alerting configuration and custom alert channels
- [ ] Changelog for release

### API Endpoint Tests
- [ ] Unit tests for `MetricsCollector` -- verify correct metrics recorded for each event type
- [ ] Unit tests for `RedisMetricsStore` -- read/write round-trip for all metric types
- [ ] Unit tests for `DatabaseMetricsStore` -- same coverage as Redis store
- [ ] Unit tests for `MetricsPruner` -- verify old data removed, recent data preserved
- [ ] Unit tests for `QueueSizeRecorder` -- verify snapshots stored correctly
- [ ] Unit tests for `WorkerMonitor` -- heartbeat recording, staleness detection
- [ ] Unit tests for all controller endpoints -- response codes, response shapes, error handling
- [ ] Unit tests for `LoomAdminGuard` -- allow/deny based on configuration
- [ ] Unit tests for `LoomServiceProvider` -- correct bindings registered
- [ ] Unit tests for `LoomConfig` -- defaults, overrides, validation
- [ ] Unit tests for `AlertManager` and `ThresholdChecker`
- [ ] Integration test: dispatch job -> worker processes -> metrics recorded -> API returns data
- [ ] Integration test: job fails -> appears in failed list -> retry via API -> re-queued
- [ ] Integration test: SSE endpoint streams events when jobs are processed
- [ ] Integration test: metrics pruner respects retention period
- [ ] Performance test: metrics store handles 1000+ jobs/sec throughput recording without backpressure

### Frontend E2E Tests
- [ ] E2E test: dashboard renders with mock API data and charts display
- [ ] E2E test: jobs list pagination and filtering works correctly
- [ ] E2E test: failed job retry button triggers API call and updates UI
- [ ] E2E test: failed job delete button triggers API call and removes row
- [ ] E2E test: bulk retry-all and delete-all with confirmation dialog
- [ ] E2E test: job detail page renders payload and exception correctly
- [ ] E2E test: SSE reconnection behavior on connection drop
- [ ] E2E test: dark/light theme toggle persists across page navigation
- [ ] E2E test: responsive layout adapts to mobile viewport
- [ ] E2E test: CSV export downloads file with correct content


## Phase 5: CLI Terminal UI (TUI)

### Interactive TUI
- [ ] `php lattice loom` — main interactive TUI entry point
- [ ] Dashboard view: live-updating queue stats (jobs/min, pending, failed, workers)
- [ ] Jobs list view: scrollable table with status colors, auto-refresh
- [ ] Failed jobs view: scrollable list with exception preview
- [ ] Job detail panel: show payload (pretty-printed JSON), exception, stack trace
- [ ] Worker status view: list active workers with heartbeat, memory, uptime
- [ ] Queue size bars: horizontal bar chart showing queue depths
- [ ] Live mode: auto-refreshing stats every N seconds
- [ ] Retry failed job: select + confirm prompt
- [ ] Retry all / delete all failed: bulk action with confirmation
- [ ] Keyboard shortcuts: `q` quit, `r` refresh, `tab` switch views, `/` search, `?` help

### Non-Interactive CLI Commands
- [ ] `php lattice loom:stats` — non-interactive: print queue stats table
- [ ] `php lattice loom:jobs` — non-interactive: list recent jobs
- [ ] `php lattice loom:failed` — non-interactive: list failed jobs
- [ ] `php lattice loom:retry <id>` — non-interactive: retry a failed job
- [ ] `php lattice loom:retry-all` — non-interactive: retry all failed
- [ ] `php lattice loom:workers` — non-interactive: list active workers
- [ ] `php lattice loom:purge` — non-interactive: delete all failed jobs with confirmation

### TUI Polish
- [ ] Color-coded job states (pending=yellow, processing=blue, completed=green, failed=red)
- [ ] Compact mode for narrow terminals
- [ ] Tests for all CLI commands
- [ ] Documentation
