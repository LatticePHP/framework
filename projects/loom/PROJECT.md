# Lattice Loom

**Loom weaves job threads through the lattice. Queue monitoring dashboard for LatticePHP.**

## Overview

Lattice Loom is a queue monitoring and management dashboard for LatticePHP applications. It provides real-time visibility into job throughput, runtime, wait times, failures, and worker health across all queues and connections managed by `lattice/queue`.

Inspired by [Laravel Horizon](https://laravel.com/docs/horizon), Lattice Loom adapts the concept to LatticePHP's module-based architecture. It ships as a self-contained package (`lattice/loom`) that registers a `LoomModule` exposing API endpoints and two interfaces: a **Web SPA** (browser-based single-page application) and a **Rich CLI TUI** (terminal user interface via `php lattice loom`) for environments where a browser is unavailable or a quick terminal check is preferred.

## Problem Statement

When applications rely heavily on background job processing, operators need answers to:

- **Are jobs being processed?** Throughput metrics over time.
- **How fast?** Average runtime and wait time per queue.
- **What failed?** Full exception traces, payloads, and one-click retry.
- **Are workers alive?** Heartbeat monitoring and process status.
- **Is the system healthy?** Queue depth trending, failure rate spikes.

Without a dedicated dashboard, diagnosing queue issues requires ad-hoc Redis/database queries, log tailing, and guesswork. Loom replaces all of that with a single pane of glass.

## Features

### Job Metrics
- **Throughput** -- jobs processed per minute/hour, graphed over time.
- **Runtime** -- average, p50, p95, p99 execution time per queue.
- **Wait time** -- how long jobs sit in the queue before a worker picks them up.
- **Failure rate** -- percentage of jobs that fail, tracked per queue and globally.

### Failed Job Management
- Browse all failed jobs with queue, class, and failure timestamp.
- View the full serialized payload and exception stack trace.
- **Retry** a single failed job or retry all failed jobs in bulk.
- **Delete** a single failed job or flush all failed jobs.

### Real-Time Monitoring
- Server-Sent Events (SSE) endpoint pushes live updates to the dashboard.
- New jobs, completions, and failures appear without polling.
- Auto-reconnect on connection drop with configurable heartbeat interval.

### Queue and Connection Health
- Current queue depth (pending job count) per queue name.
- Historical queue size over time to detect growing backlogs.
- Per-connection status (e.g., Redis connectivity, database availability).

### Worker Process Monitoring
- List of active workers with their queue assignments.
- Worker heartbeat tracking -- detect stale/dead workers.
- Memory usage and uptime per worker.
- Worker start/stop events logged.

### Job Payload Inspection
- View the serialized job class, constructor arguments, and metadata.
- See attempt count, max attempts, timeout, delay, and `availableAt` timestamp.
- Inspect the full `SerializedJob` record as stored by `lattice/queue`.

### Tag-Based Filtering
- Assign tags to jobs (e.g., `tenant:42`, `priority:high`).
- Filter the jobs list and metrics by one or more tags.
- Tag-based throughput and failure breakdowns.

### Rate Metrics with Charts
- Time-series charts for throughput, runtime, wait time, and failure rate.
- Selectable time windows: last 5 minutes, 1 hour, 24 hours, 7 days.
- Per-queue and aggregate views.

## Architecture

```
+---------------------+       +-------------------+       +------------------+
|   Queue Workers     |       |   Loom            |       |   Frontend SPA   |
|   (lattice/queue)   | ----> |   Event           | ----> |   (Vanilla JS /  |
|                     |       |   Listeners        |       |    Preact)       |
|  Dispatched         |       |                   |       |                  |
|  Processing         |       |  Collects metrics |       |  Dashboard       |
|  Processed          |       |  Stores in Redis  |       |  Jobs list       |
|  Failed             |       |                   |       |  Job detail      |
+---------------------+       +-------------------+       |  Metrics charts  |
                                      |                   |  Worker status   |
                                      v                   +--------+---------+
                              +-------------------+                |
                              |   Redis / DB      |                |
                              |   Metrics Store   | <--------------+
                              |                   |       (API + SSE)
                              |  Sorted sets for  |
                              |  time-series data |
                              |  Hash maps for    |
                              |  job snapshots    |
                              +-------------------+
```

### Layer Breakdown

#### 1. Metrics Collection Layer

Event listeners hook into `lattice/queue` lifecycle events:

| Event             | Data Captured                                                  |
|-------------------|----------------------------------------------------------------|
| `job.dispatched`  | Job ID, class, queue, tags, timestamp                          |
| `job.processing`  | Job ID, worker ID, queue, timestamp (start wait-time clock)    |
| `job.processed`   | Job ID, queue, runtime (ms), timestamp                         |
| `job.failed`      | Job ID, queue, exception class, message, trace, attempt count  |

Listeners compute derived metrics (throughput windows, rolling averages) and write them into the metrics store.

#### 2. Metrics Storage Layer

Primary storage is **Redis** (via `lattice/cache` with `RedisCacheDriver`):

- **Sorted sets** for time-series data keyed by minute bucket (`loom:throughput:{queue}:{minute}`).
- **Hash maps** for current worker state (`loom:workers:{id}`).
- **Lists** for recent job snapshots (`loom:recent:{queue}`).
- **Strings/counters** for aggregate stats (`loom:stats:processed`, `loom:stats:failed`).

A configurable **retention policy** prunes data older than N hours/days to keep Redis memory bounded.

Fallback: a database-backed store for environments without Redis.

#### 3. API Layer

A `LoomModule` registers controllers that expose REST endpoints under a configurable prefix (default: `/loom/api`). All endpoints are JSON and protected by an admin guard (configurable middleware).

#### 4. SSE Layer

A dedicated SSE endpoint (`/loom/api/events`) streams real-time updates. The server side uses a loop that reads from Redis pub/sub or polls the metrics store at a short interval. Events are typed (`job.completed`, `job.failed`, `metrics.updated`, `worker.heartbeat`) so the frontend can dispatch them to the appropriate UI components.

#### 5. Frontend SPA

A lightweight single-page application served from `/loom`. Built with Preact (or vanilla JS with a minimal reactive layer), bundled into static assets that the `LoomModule` serves. The frontend consumes the API and SSE endpoints exclusively -- no server-rendered HTML.

## Dependencies

| Package            | Purpose                                                      |
|--------------------|--------------------------------------------------------------|
| `lattice/queue`    | Core queue system -- job dispatching, workers, failed store  |
| `lattice/events`   | Event dispatcher for hooking into job lifecycle events       |
| `lattice/cache`    | Redis connection for metrics storage via `RedisCacheDriver`  |
| `lattice/http`     | Request/Response, controllers, middleware for API endpoints  |
| `lattice/module`   | `#[Module]` attribute for registering `LoomModule`           |
| `lattice/compiler` | Compile-time module discovery and DI wiring                  |
| `lattice/ripple`   | (optional — for WebSocket real-time instead of SSE)          |

### Integration Points with `lattice/queue`

Loom builds directly on top of the existing queue abstractions:

- **`QueueDriverInterface`** -- calls `size()` for queue depth, no driver modifications needed.
- **`FailedJobStoreInterface`** -- reads failed jobs via `all()`, `find()`, retries via `retry()`, deletes via `delete()` and `flush()`.
- **`SerializedJob`** -- inspects `id`, `queue`, `payload`, `attempts`, `maxAttempts`, `timeout`, `availableAt`, `createdAt` for the job detail view.
- **`Worker`** -- Loom wraps or decorates the worker to emit lifecycle events (`job.processing`, `job.processed`, `job.failed`) that the metrics collection layer listens to.
- **`Dispatcher`** -- listens for `job.dispatched` events emitted when `dispatch()` or `dispatchAfter()` is called.

## Configuration

```php
// config/loom.php
return [
    'prefix' => '/loom',
    'middleware' => ['auth:admin'],
    'storage' => [
        'driver' => 'redis',       // 'redis' or 'database'
        'connection' => 'default',
        'prefix' => 'loom:',
    ],
    'metrics' => [
        'retention' => 24 * 7,     // hours to keep metrics data
        'snapshot_interval' => 60, // seconds between metric snapshots
        'trim_interval' => 300,    // seconds between retention cleanup runs
    ],
    'sse' => [
        'heartbeat' => 15,         // seconds between SSE heartbeat pings
        'retry' => 3000,           // ms client retry on disconnect
    ],
    'alerting' => [
        'enabled' => false,
        'failure_threshold' => 0.1, // alert if failure rate > 10%
        'queue_depth_threshold' => 1000,
        'channels' => ['log'],      // notification channels
    ],
];
```

## API Endpoints

| Method   | Path                              | Description                          |
|----------|-----------------------------------|--------------------------------------|
| `GET`    | `/api/loom/stats`                 | Dashboard summary stats              |
| `GET`    | `/api/loom/jobs/recent`           | Recent jobs (paginated)              |
| `GET`    | `/api/loom/jobs/pending`          | Pending jobs (paginated)             |
| `GET`    | `/api/loom/jobs/completed`        | Completed jobs (paginated)           |
| `GET`    | `/api/loom/jobs/failed`           | Failed jobs (paginated)              |
| `GET`    | `/api/loom/jobs/{id}`             | Single job detail                    |
| `POST`   | `/api/loom/jobs/{id}/retry`       | Retry a failed job                   |
| `DELETE` | `/api/loom/jobs/{id}`             | Delete a failed job                  |
| `POST`   | `/api/loom/jobs/retry-all`        | Retry all failed jobs                |
| `DELETE` | `/api/loom/jobs/failed`           | Delete all failed jobs               |
| `GET`    | `/api/loom/queues`                | Queue list with current depths       |
| `GET`    | `/api/loom/queues/{name}/metrics` | Metrics for a specific queue         |
| `GET`    | `/api/loom/workers`               | Active workers list                  |
| `GET`    | `/api/loom/metrics/throughput`    | Throughput time-series               |
| `GET`    | `/api/loom/metrics/runtime`       | Runtime time-series                  |
| `GET`    | `/api/loom/metrics/wait`          | Wait time time-series                |
| `GET`    | `/api/loom/events`                | SSE stream for real-time updates     |

## Inspiration

Laravel Horizon provides a beautiful queue monitoring dashboard for Laravel applications using Redis. Lattice Loom adapts this concept for LatticePHP with the following differences:

- **Module-based architecture** -- registered as a `#[Module]` with the Lattice compiler, not a Laravel service provider.
- **Driver-agnostic monitoring** -- while Redis is preferred for metrics storage, the monitoring layer works with any `QueueDriverInterface` implementation (database, Redis, SQS via Illuminate bridge).
- **SSE instead of polling** -- the frontend uses Server-Sent Events for real-time updates rather than short-polling.
- **Lightweight frontend** -- Preact or vanilla JS instead of Vue.js, keeping the bundle small.
- **No supervisor process** -- Loom monitors existing workers via heartbeats rather than managing worker processes directly. Worker lifecycle management remains the responsibility of the deployment environment (systemd, Docker, etc.).

## Package Structure

```
packages/loom/
  composer.json
  src/
    LoomModule.php               # Module definition with controllers and providers
    LoomServiceProvider.php      # Registers event listeners, metrics store, config
    Config/
      LoomConfig.php             # Typed configuration object
    Events/
      JobDispatched.php
      JobProcessing.php
      JobProcessed.php
      JobFailed.php
      WorkerHeartbeat.php
    Listeners/
      MetricsCollector.php       # Listens to job events, writes metrics
      WorkerMonitor.php          # Tracks worker heartbeats
    Metrics/
      MetricsRepository.php      # Read interface for metrics data
      MetricsWriter.php          # Write interface for metrics data
      RedisMetricsStore.php      # Redis implementation
      DatabaseMetricsStore.php   # Database fallback
      Snapshot.php               # Point-in-time metrics snapshot
      TimeSeriesPoint.php        # Single data point (timestamp + value)
    Http/
      Controllers/
        DashboardController.php
        JobsController.php
        QueuesController.php
        WorkersController.php
        MetricsController.php
        EventStreamController.php
      Middleware/
        LoomAdminGuard.php
    Alerting/
      AlertManager.php
      ThresholdChecker.php
  resources/
    dist/                        # Compiled frontend assets
      index.html
      app.js
      app.css
  frontend/                      # Frontend source (not shipped in package)
    src/
      app.jsx
      pages/
      components/
      hooks/
```
