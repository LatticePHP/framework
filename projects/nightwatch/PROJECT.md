# Nightwatch — Unified Monitoring for LatticePHP

## What Is Nightwatch?

Nightwatch is a **single, unified monitoring tool** for LatticePHP that replaces the need for two separate dashboards. Where Laravel requires three separate packages (Telescope for debugging, Pulse for production metrics, and now Nightwatch as a third tool), LatticePHP ships **one package** that covers both use cases.

Nightwatch automatically adapts its behavior based on your environment:

- **Dev mode** — acts like a request inspector / debugger (individual entry storage)
- **Prod mode** — acts like a metrics dashboard (aggregated time-series data)

One install. One config. One dashboard. Two modes.

---

## Why File-System Storage?

Nightwatch stores everything on the local file system. No database tables, no Redis, no external services.

**Advantages:**

- **Zero infrastructure** — works out of the box with no setup beyond `composer require`
- **Infinitely cheap** — disk space is the only cost; no database connections consumed
- **Easy pruning** — delete old data by removing directories (`rm -rf storage/nightwatch/requests/2026/01/`)
- **Natural time-based partitioning** — the directory structure *is* the partition scheme
- **No migration headaches** — no schema to maintain, no migrations to run
- **Append-only writes** — no read-modify-write cycles, no locking contention
- **GZIP compression** — 70-90% size reduction on NDJSON data

---

## Storage Architecture

### Directory Layout

```
storage/nightwatch/
  requests/2026/03/22/14/events.ndjson.gz
  queries/2026/03/22/14/events.ndjson.gz
  exceptions/2026/03/22/14/events.ndjson.gz
  cache/2026/03/22/14/events.ndjson.gz
  jobs/2026/03/22/14/events.ndjson.gz
  mail/2026/03/22/14/events.ndjson.gz
  logs/2026/03/22/14/events.ndjson.gz
  metrics/2026/03/22/14/aggregates.json
```

### Format: NDJSON + GZIP

- **NDJSON** (Newline-Delimited JSON) — each line is a self-contained JSON object
- **Append-only** — new entries are appended; files are never overwritten or rewritten
- **GZIP compressed** — `.ndjson.gz` files achieve 70-90% size reduction
- **Hourly partitioning** — one file per type per hour, organized as `{type}/{year}/{month}/{day}/{hour}/`
- **Pruning** — retention is enforced by deleting entire date directories older than the configured TTL

### Metrics Aggregates

Production metrics are stored as pre-computed aggregates in `aggregates.json` files, bucketed at multiple resolutions:

- **1-minute** buckets — recent fine-grained data
- **5-minute** buckets — short-term trends
- **1-hour** buckets — daily patterns
- **1-day** buckets — long-term overview

### Concurrency Strategy

GZIP files do not support true atomic appends. Nightwatch handles this by:

1. **Write buffering**: Each PHP-FPM worker buffers entries in memory, flushing every N entries or on request shutdown
2. **Per-process temp files**: Each worker writes to a process-specific temp file (`events.{pid}.ndjson`), avoiding lock contention
3. **Periodic merge**: A background process merges per-process files into the hourly GZIP archive, running every minute via scheduler
4. **Newest-first reading**: Index files track byte offsets per entry, enabling efficient reverse-chronological reads without full decompression

This approach eliminates file locking under concurrent writes while maintaining the append-only, cost-efficient storage model.

---

## Two Modes

### Dev Mode (Debug Inspector)

When `APP_ENV=local` or `APP_ENV=dev`, Nightwatch stores **individual entries** for every event — similar to Laravel Telescope:

- **Request inspector** — full request/response with headers, duration, status code
- **Query log** — SQL with bindings, duration, caller location, slow query highlighting
- **Exception viewer** — class, message, full stack trace, request context
- **Event/listener log** — dispatched events and their listeners
- **Cache monitor** — hits, misses, writes, forgets
- **Queue job log** — job class, payload, status, duration
- **Mail preview** — sent mail with rendered HTML preview
- **Model watcher** — created/updated/deleted with attribute diffs
- **Gate watcher** — authorization checks with pass/fail results
- **Log viewer** — application log entries with level

### Prod Mode (Metrics Dashboard)

When `APP_ENV=production` or `APP_ENV=staging`, Nightwatch stores **aggregated metrics** — similar to Laravel Pulse:

- **Request latency** — P99, P95, P50 response times per endpoint
- **Slow queries** — normalized SQL ranked by frequency and duration
- **Exception counts** — exception class frequency with trends
- **Cache hit ratio** — hit/miss ratio over time
- **Queue throughput** — jobs processed, wait time, failure rate
- **Server vitals** — CPU usage, memory consumption, disk utilization

Prod mode supports **configurable sampling rates** to minimize overhead.

---

## Two Interfaces

### Web SPA

A unified single-page application served from `/nightwatch` (configurable). The dashboard auto-detects the current mode:

- **Dev view** — tabbed interface for browsing individual entries (requests, queries, exceptions, events, cache, jobs, mail, logs, models, gates), batch/timeline view, detail panels
- **Prod view** — card grid with metrics (slow requests, slow queries, top exceptions, cache ratio, queue throughput, server vitals), time range picker, auto-refresh
- Dark and light themes, responsive layout

### CLI Terminal UI (TUI)

A rich interactive terminal dashboard launched via `php lattice nightwatch`:

- **Dev mode** — scrollable entry lists per type, detail panels, slow query highlighting, stack trace viewer
- **Prod mode** — metrics cards, sparklines, gauges for CPU/memory/disk, P99 tables
- **Live tail** — `php lattice nightwatch --tail` streams new entries in real time
- Non-interactive subcommands for scripting: `nightwatch:requests`, `nightwatch:queries`, `nightwatch:exceptions`, `nightwatch:logs`, `nightwatch:metrics`, `nightwatch:servers`, `nightwatch:prune`, `nightwatch:toggle`

---

## Dependencies

| Package                | Purpose                                          |
|------------------------|--------------------------------------------------|
| `lattice/observability`| Event hooks, span tracking, metric collection    |
| `lattice/http`         | HTTP kernel integration, request/response access |
| `lattice/pipeline`     | Middleware pipeline for watcher/recorder chain   |
| `lattice/ripple`       | (optional — for WebSocket real-time instead of SSE) |

---

## How Nightwatch Differs from Laravel

| Concern             | Laravel                                      | LatticePHP                        |
|---------------------|----------------------------------------------|-----------------------------------|
| Debug inspector     | Telescope (separate package)                 | Nightwatch dev mode               |
| Production metrics  | Pulse (separate package)                     | Nightwatch prod mode              |
| Storage backend     | Database tables (Telescope) / Database (Pulse)| File system (NDJSON + GZIP)      |
| Setup               | Migrations + database config per package     | Zero config — uses disk           |
| Dashboards          | Two separate web UIs                         | One unified SPA + CLI TUI         |
| CLI tooling          | Limited Artisan commands                     | Full interactive TUI + live tail  |
| Packages to install | 2-3                                          | 1                                 |

Nightwatch is one tool that does the job of two (or three), with simpler infrastructure requirements and a better developer experience.
