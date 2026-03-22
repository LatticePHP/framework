# 08 — Loom: Queue Monitoring Dashboard

> Real-time queue monitoring, job management, and worker health dashboard for LatticePHP with a Next.js SPA and CLI TUI

## Dependencies
- `lattice/queue` (core queue system — job dispatching, workers, failed store)
- `lattice/events` (event dispatcher for hooking into job lifecycle events)
- `lattice/cache` (Redis connection for metrics storage via `RedisCacheDriver`)
- `lattice/http` (request/response, controllers, middleware for API endpoints)
- `lattice/module` (`#[Module]` attribute for registering `LoomModule`)
- `lattice/compiler` (compile-time module discovery and DI wiring)
- Optional: `lattice/ripple` (for WebSocket real-time instead of SSE)

## Frontend Stack
- **Next.js** (React framework) or React SPA
- **NextUI** (component library)
- **TailwindCSS** (utility-first styling, dark/light theming)
- **TanStack Query** (server-state caching, automatic refetching, SSE integration)
- **TanStack Router** (if standalone SPA) or Next.js App Router
- **Zustand** (client-side state management)
- **Zod** (runtime validation for API responses)

## Subtasks

### 1. [ ] Metrics collection — job event listeners, Redis storage, failed jobs

#### Job Lifecycle Event Listeners
- Define `JobDispatched` event class: job ID, class name, queue, tags, timestamp
- Define `JobProcessing` event class: job ID, worker ID, queue, timestamp
- Define `JobProcessed` event class: job ID, queue, runtime (ms), timestamp
- Define `JobFailed` event class: job ID, queue, exception class, message, trace, attempt count, timestamp
- Define `JobReleased` event class: job ID, queue, delay, attempt count, timestamp
- Create `MetricsCollector` listener subscribing to all job lifecycle events
- Instrument `Dispatcher::dispatch()` to fire `JobDispatched` event via `lattice/events`
- Instrument `Worker::processJob()` to fire `JobProcessing` before and `JobProcessed`/`JobFailed` after
- Add tag extraction from job class (support a `HasTags` interface)
- Unit tests for each event class construction and serialization

#### Metrics Storage (Redis)
- Define `MetricsWriterInterface`: `recordJobProcessed()`, `recordJobFailed()`, `recordJobDispatched()`, `recordWaitTime()`, `recordRuntime()`, `incrementThroughput()`
- Define `MetricsRepositoryInterface`: `getThroughput()`, `getRuntime()`, `getWaitTime()`, `getFailureRate()`, `getQueueSizes()`, `getRecentJobs()`, `getSnapshot()`
- Implement `RedisMetricsStore` using `lattice/cache` `RedisCacheDriver`:
  - Sorted sets for throughput time-series: `loom:throughput:{queue}:{minute_bucket}`
  - Sorted sets for runtime time-series: `loom:runtime:{queue}:{minute_bucket}`
  - Sorted sets for wait-time time-series: `loom:wait:{queue}:{minute_bucket}`
  - Redis hash for aggregate counters: `loom:stats` (processed, failed, total_runtime, total_wait)
  - Redis list for recent job snapshots: `loom:recent:{queue}` (capped at configurable length)
  - Redis hash per worker: `loom:workers:{worker_id}` (queue, pid, memory, last_heartbeat, jobs_processed)
- Implement `DatabaseMetricsStore` as fallback (no Redis):
  - Migration for `loom_metrics` table (timestamp, queue, metric_type, value)
  - Migration for `loom_recent_jobs` table (job_id, queue, class, status, runtime, created_at)
  - Migration for `loom_workers` table (worker_id, queue, pid, memory, last_heartbeat)
- Unit tests for `RedisMetricsStore` read/write cycle using `FakeRedisDriver`
- Unit tests for `DatabaseMetricsStore` using in-memory SQLite

#### Failed Job Storage
- Integrate with existing `FailedJobStoreInterface` from `lattice/queue`
- Store full job payload alongside failure record for inspection
- Store exception class, message, file, line, and full stack trace as structured data
- Enrich failed job data with Loom-specific metadata (tags, runtime before failure, worker ID)
- Index failed jobs by queue and by tag for filtered retrieval
- Add `getFailedByQueue()` and `getFailedByTag()` to `MetricsRepositoryInterface`
- Unit tests for enriched failed job storage and retrieval

#### Queue Size Polling
- Implement `QueueSizeRecorder` using `QueueDriverInterface::size()` on a timer (configurable, default 60s)
- Store queue size time-series in Redis sorted set: `loom:queue_size:{queue}:{minute_bucket}`
- Auto-discover active queues from metrics data
- Unit tests for queue size recording and retrieval

- **Verify:** Job lifecycle events fire correctly; `RedisMetricsStore` records and retrieves throughput, runtime, and failure metrics; failed jobs include full payload and exception data

### 2. [ ] Worker heartbeat tracking + retention/cleanup

#### Worker Heartbeat Tracking
- Define `WorkerStarted` event: worker ID, queue, PID, timestamp
- Define `WorkerStopped` event: worker ID, queue, reason, timestamp
- Define `WorkerHeartbeat` event: worker ID, queue, memory usage, jobs processed count, timestamp
- Create `WorkerMonitor` listener subscribing to worker events
- Workers send heartbeat every N seconds (configurable, default 15s)
- Heartbeat includes: worker ID, queue, PID, memory (MB), uptime, jobs processed since start
- Update Redis hash `loom:workers:{id}` on each heartbeat
- Register worker on start, update on heartbeat, unregister on stop
- Detect stale workers: last heartbeat > 2x interval, mark as `inactive`
- Remove worker entry after configurable timeout (default 5 minutes)
- Generate unique worker ID: hostname + PID + random suffix
- Unit tests for heartbeat recording, staleness detection, cleanup

#### Retention Policy and Cleanup
- Create `MetricsPruner` for removing data older than configured retention period
- Prune throughput, runtime, wait-time, and queue-size sorted sets
- Trim recent jobs lists to max configured length (default 10,000)
- Clean up stale worker hashes
- Run pruner on configurable interval (default every 5 minutes)
- `php lattice loom:purge` CLI command for manual pruning
- Unit tests for pruner with time manipulation

#### LoomServiceProvider
- Create `LoomServiceProvider` implementing provider interface
- Register `LoomConfig` from `config/loom.php`
- Bind `MetricsWriterInterface` and `MetricsRepositoryInterface` to configured implementation
- Register `MetricsCollector`, `WorkerMonitor`, `MetricsPruner`, `QueueSizeRecorder`
- Auto-register all event listeners without manual wiring
- Conditionally register alerting services if `alerting.enabled` is true
- Unit tests for service provider registration and configuration binding

- **Verify:** Worker heartbeats update Redis; stale workers are detected after 2x interval; pruner removes old metrics data; service provider registers all components correctly

### 3. [ ] API layer — LoomModule, stats, jobs CRUD, failed job actions, metrics

#### LoomModule
- Create `LoomModule` with `#[Module]` attribute
- Register all controllers: `DashboardController`, `JobsController`, `QueuesController`, `WorkersController`, `MetricsController`, `EventStreamController`
- Register `LoomServiceProvider` as provider
- Create `LoomAdminGuard` middleware: supports callback, gate/policy, role check
- Default guard: deny all in production, allow all in development
- Route prefix configurable via `loom.prefix` (default `/loom`)
- Static asset serving for frontend SPA
- Catch-all route for SPA client-side routing (`/loom/{any}` returns `index.html`)
- Unit tests for module registration, admin guard, route prefix

#### Stats Endpoint
- `GET /api/loom/stats` — dashboard overview
- Return: jobs processed (last hour), jobs failed (last hour), total processed, total failed, throughput (jobs/min), avg runtime (ms), avg wait (ms), active workers, queue sizes
- Support `?period=1h|6h|24h|7d` for time window
- Cache response for 5 seconds
- Unit tests for response structure and period parameter

#### Jobs Endpoints
- `GET /api/loom/jobs/recent` — paginated recently processed jobs (default 25/page)
  - Filter: `?queue=default&search=ClassName`
  - Fields: id, class, queue, status, attempts, runtime, created_at, completed_at
- `GET /api/loom/jobs/failed` — paginated failed jobs
  - Filter: `?queue=default&search=ClassName`
  - Fields: id, class, queue, exception class, exception message, attempts, failed_at
  - Search across job class, exception class, exception message
- `GET /api/loom/jobs/pending` — paginated pending jobs by queue
  - Fields: id, class, queue, created_at, available_at, estimated wait time
- `GET /api/loom/jobs/:id` — full job detail
  - Fields: id, class, queue, connection, status, payload (deserialized JSON), attempts, max_attempts, timeout, timestamps, runtime
  - Failed jobs: exception class, message, trace (array of frames), failed_at
  - Pending jobs: position in queue, estimated wait time
- Unit tests for all jobs endpoints (pagination, filtering, search, 404)

#### Failed Job Actions
- `POST /api/loom/jobs/:id/retry` — retry single failed job
  - Return `{ "status": "retried", "job_id": "..." }` or 404
- `POST /api/loom/jobs/retry-all` — retry all failed jobs
  - Return `{ "status": "retried", "count": N }`
- `DELETE /api/loom/jobs/:id` — delete single failed job
  - Return `{ "status": "deleted" }` or 404
- Unit tests for retry, retry-all, delete, 404 handling

#### Metrics Endpoints
- `GET /api/loom/metrics/throughput` — time-series of jobs per minute
  - Support `?period=5m|1h|24h|7d`, `?granularity=minute|hour`, queue filter
- `GET /api/loom/metrics/runtime` — time-series of avg runtime
  - Support period, granularity, p50/p95/p99 percentile breakdowns
- `GET /api/loom/workers` — active workers list
  - Fields: id, queue, status (active/inactive), pid, memory_mb, uptime, jobs_processed, last_heartbeat
  - Sort: active first, then by last heartbeat descending
- Unit tests for all metrics and workers endpoints

- **Verify:** `GET /api/loom/stats` returns current metrics; jobs endpoints return paginated data with filters; retry re-enqueues a failed job; workers endpoint lists active workers

### 4. [ ] API — SSE real-time stream

- `GET /api/loom/events` — Server-Sent Events endpoint
- Set headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`
- Event types:
  - `job.processed`: `{ "id", "class", "queue", "runtime_ms" }`
  - `job.failed`: `{ "id", "class", "queue", "exception", "message" }`
  - `metrics.snapshot`: `{ "throughput", "failure_rate", "avg_runtime", "active_workers" }` (every N seconds)
  - `worker.status`: `{ "worker_id", "status", "queue", "memory_mb" }` (on heartbeat)
  - `queue.size`: `{ "queue", "size" }` (on size change)
- Send heartbeat comment (`: heartbeat\n\n`) every 15 seconds
- Support `Last-Event-ID` header for reconnection and missed event replay
- Use Redis pub/sub channel `loom:events` for cross-process distribution
- Fallback to polling metrics store if Redis pub/sub unavailable
- Integration test for SSE connection and event reception
- Unit test for event serialization format
- **Verify:** SSE endpoint streams `job.processed` events when jobs complete; `metrics.snapshot` arrives periodically; client reconnection with `Last-Event-ID` replays missed events

### 5. [ ] Frontend — dashboard page (metrics cards, throughput chart, queue sizes)

#### Project Setup
- Initialize Next.js project with TypeScript in `projects/loom/frontend/`
- Install and configure NextUI, TailwindCSS (dark/light), TanStack Query, Zustand, Zod
- Configure path aliases, ESLint + Prettier, Vitest
- Configure development proxy to LatticePHP backend
- Configure production build output to `resources/dist/`

#### API Client Layer
- Create typed HTTP client using TanStack Query
- Define Zod schemas for all API response types
- Create query hooks: `useStats()`, `useRecentJobs()`, `useFailedJobs()`, `usePendingJobs()`, `useJob(id)`, `useThroughputMetrics()`, `useRuntimeMetrics()`, `useWorkers()`
- Create mutation hooks: `useRetryJob()`, `useRetryAll()`, `useDeleteJob()`
- Create SSE connection manager hook with auto-reconnect

#### Layout Shell
- Sidebar navigation (NextUI): Dashboard, Jobs (Recent, Failed, Pending), Metrics, Workers
- Header with connection status indicator (connected/reconnecting/disconnected) and key stats summary
- Collapsible sidebar for mobile/tablet
- Dark/light theme toggle (Zustand + localStorage + OS preference)
- Light theme default for Loom (queue monitoring convention)

#### Dashboard Page
- Key metrics cards row (NextUI `Card`): total processed, total failed, throughput/min, avg runtime, avg wait, active workers
- Throughput chart: line chart showing jobs processed per minute over selected period
- Failure rate chart: line chart showing failure percentage over time
- Queue depth chart: stacked area chart showing pending jobs per queue
- Period selector: 5m, 1h, 24h, 7d toggle
- Auto-update metrics cards via SSE `metrics.snapshot` events
- Loading skeleton states (NextUI `Skeleton`) while data is fetching
- Error state with retry button if API is unreachable

- **Verify:** Dashboard renders metrics cards with data from API; throughput chart updates; SSE events update card values in real-time; period selector changes chart timeframe

### 6. [ ] Frontend — jobs pages (recent, failed, pending), job detail page

#### Recent Jobs Page
- Jobs table (NextUI `Table`): status icon, job class, queue, runtime, attempts, timestamp
- Click row to navigate to job detail
- Sort by column (timestamp, runtime, attempts)
- Pagination (NextUI `Pagination`) with page size selector
- Filter bar: queue dropdown (NextUI `Select`), search text field (NextUI `Input`)
- Auto-refresh at configurable interval (5s, 15s, 30s, 60s, off)
- Real-time new job indicator via SSE ("N new jobs" badge, click to refresh)
- Empty state with helpful message

#### Failed Jobs Page
- Failed jobs table: status icon, job class, queue, exception, attempts, failed_at
- Individual row actions: retry button (NextUI `Button`), delete button with confirmation (NextUI `Modal`)
- Bulk actions toolbar: "Retry All" and "Delete All" with confirmation dialog
- Search bar for class name or exception message filtering
- Pagination controls
- Success/error feedback toast (NextUI `toast` or similar) on retry/delete
- Empty state

#### Pending Jobs Page
- Per-queue sections showing pending job count and list
- Each entry: job class, created_at, estimated wait time
- Queue depth summary cards or bar chart
- Auto-refresh to track queue drain

#### Job Detail Page
- Header: job class name, status badge (NextUI `Chip`), queue badge
- Metadata section: ID, connection, attempts/max, timeout, timestamps, runtime
- Payload viewer: syntax-highlighted JSON tree of serialized job properties
- Collapsible sections for large payloads (NextUI `Accordion`)
- For failed jobs: exception panel with class, message, full stack trace (file paths, line numbers)
- Retry button (failed only) with loading state and feedback
- Delete button (failed only) with confirmation
- "Back to list" navigation, copy job ID / copy payload buttons

- **Verify:** Recent jobs page renders table, filters by queue, search works; failed jobs page retry button re-enqueues job and updates UI; job detail page shows payload and exception trace

### 7. [ ] Frontend — metrics charts (throughput over time, runtime) + workers page

#### Metrics Page
- Throughput over time: line chart with queue selector (all queues or specific queue)
- Average runtime over time: line chart with p50/p95/p99 lines
- Wait time over time: line chart per queue
- Failure rate over time: line chart with threshold reference line
- Queue depth over time: stacked area chart
- Configurable time range selector: 5m, 1h, 6h, 24h, 7d
- Responsive charts that resize with container
- Tooltip on hover showing exact values and timestamp
- Use a lightweight chart library (Chart.js, Recharts, or similar)

#### Workers Page
- Worker status table (NextUI `Table`): ID, queue, status badge (active/inactive/stale), PID, memory, uptime, jobs processed, last heartbeat
- Color-coded status badges: green (active), gray (inactive), red (stale/dead)
- Time-since-heartbeat display updating in real-time
- SSE-driven updates for worker status changes
- Empty state when no workers are running

#### SSE Integration (Global)
- SSE connection manager: connect, auto-reconnect with exponential backoff, max retries
- Connection status indicator in header: green (connected), yellow (reconnecting), red (disconnected)
- Route SSE events to appropriate page state via Zustand
- Debounce rapid updates (batch every 500ms)
- Pause SSE when browser tab is hidden, resume on visibility change

- **Verify:** Metrics charts render time-series data; queue selector filters chart data; workers page shows active/inactive workers with live heartbeat updates; SSE reconnects on disconnect

### 8. [ ] CLI TUI — `php lattice loom` interactive + non-interactive commands

#### Interactive TUI
- `php lattice loom` — main interactive TUI entry point
- Dashboard view: live-updating queue stats (jobs/min, pending, failed, workers)
- Jobs list view: scrollable table with status colors, auto-refresh
- Failed jobs view: scrollable list with exception preview
- Job detail panel: payload (pretty-printed JSON), exception, stack trace
- Worker status view: active workers with heartbeat, memory, uptime
- Queue size bars: horizontal bar chart showing queue depths
- Live mode: auto-refreshing stats every N seconds
- Retry failed job: select + confirm prompt
- Retry all / delete all: bulk action with confirmation
- Keyboard shortcuts: `q` quit, `r` refresh, `tab` switch views, `/` search, `?` help

#### Non-Interactive CLI Commands
- `php lattice loom:stats` — print queue stats table
- `php lattice loom:jobs` — list recent jobs
- `php lattice loom:failed` — list failed jobs
- `php lattice loom:retry <id>` — retry a failed job
- `php lattice loom:retry-all` — retry all failed
- `php lattice loom:workers` — list active workers
- `php lattice loom:purge` — delete all failed jobs with confirmation
- All commands support `--json` flag for machine-readable output

#### TUI Polish
- Color-coded job states: pending=yellow, processing=blue, completed=green, failed=red
- Compact mode for narrow terminals
- Unit tests for all CLI commands
- **Verify:** `php lattice loom` TUI shows live queue stats; `loom:stats` outputs formatted table; `loom:retry <id>` re-enqueues a failed job; `loom:failed` lists failed jobs

## Integration Verification
- [ ] Job lifecycle events fire and metrics are recorded in Redis when jobs are dispatched and processed
- [ ] `GET /api/loom/stats` returns accurate throughput, failure count, and worker count
- [ ] SPA dashboard renders metrics cards and throughput chart with data from API
- [ ] Failed job appears in failed jobs list; clicking retry re-enqueues it and it disappears from the list
- [ ] Job detail page displays full payload JSON and exception stack trace for a failed job
- [ ] SSE stream delivers `job.processed` and `job.failed` events as jobs complete
- [ ] Workers page shows active workers with live heartbeat updates
- [ ] Metrics pruner removes data older than configured retention period
- [ ] CLI `loom:stats` outputs match API data
- [ ] End-to-end: dispatch 100 jobs, watch throughput chart update in real-time, trigger a failure, see it appear in failed jobs list, retry it
