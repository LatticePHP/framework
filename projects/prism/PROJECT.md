# Prism -- Self-Hosted Error Reporting Platform

## Overview

Prism is a self-hosted error reporting platform built on LatticePHP -- like Sentry, BugSnag, or Rollbar, but designed for teams that want full control over their error data at near-zero cost. Instead of paying per-event pricing to a SaaS vendor, Prism stores error events as compressed, append-only files in blob storage (Azure Blob Storage, S3, or even the local filesystem) and uses Postgres only as a thin hot index for aggregation and querying.

Prism is the **observability layer** for errors across the entire stack. It ingests errors from any platform -- PHP (LatticePHP, Laravel), JavaScript (Next.js, browser), TypeScript (NestJS, Node.js) -- through lightweight, fire-and-forget SDKs. The SDKs are designed to never impact application performance: batched, asynchronous, and fail-silent.

### What Prism Does

- **Captures errors and exceptions** from any platform via lightweight SDKs
- **Deduplicates errors** into issues using fingerprinting (normalized stacktrace hashing)
- **Stores raw error events** in blob storage as compressed NDJSON -- the cheapest possible storage
- **Indexes issues** in Postgres for fast querying (fingerprint, count, first/last seen, status)
- **Streams errors in real-time** via Redis pub/sub and Ripple WebSocket to the dashboard
- **Provides a Web SPA** for browsing issues, viewing stacktraces, and managing error states
- **Provides a CLI TUI** for terminal-based error monitoring and live tail

---

## Architecture

### The Blob Storage Strategy

The core insight behind Prism's architecture is that **error event data is append-only and rarely read individually**. You write thousands of events but only read a handful when investigating an issue. This makes blob storage (Azure Blob, S3, local filesystem) the optimal storage layer:

```
Blob Storage Layout:
  {year}/{month}/{day}/{hour}/{project_id}/{environment}/{platform}/events.ndjson.gz

  Example:
  2026/03/22/14/proj_abc123/production/php/events.ndjson.gz
  2026/03/22/14/proj_abc123/production/javascript/events.ndjson.gz
  2026/03/22/15/proj_abc123/staging/php/events.ndjson.gz
```

Each file is a GZIP-compressed, newline-delimited JSON (NDJSON) file. Events are appended throughout the hour. At the end of each hour (or when the file reaches a size threshold), the file is finalized and a new one begins.

**Why NDJSON + GZIP?**
- NDJSON allows streaming reads without parsing the entire file
- GZIP typically achieves 10-20x compression on JSON stacktraces (highly repetitive text)
- Natural time-based partitioning eliminates the need for complex indexing
- Files are immutable after finalization -- no corruption risk, trivial to replicate

### Cost Analysis

For a typical application generating 10,000 errors/day:

| Component    | Sentry (Team Plan)   | Prism (Self-Hosted)            |
|-------------|----------------------|--------------------------------|
| Event volume | $26/mo for 50K events | Free (your storage)            |
| Storage      | Included (30-day retention) | ~$0.02/mo (Azure Cool Blob, 100 MB compressed) |
| Compute      | Included             | Your existing server (Prism runs as a LatticePHP module) |
| Postgres     | N/A                  | ~$0/mo (existing DB, issues table is tiny) |
| Redis        | N/A                  | ~$0/mo (existing Redis, pub/sub only) |
| **Total**    | **$26+/mo**          | **~$0.02/mo**                  |

At 100,000 events/day, Sentry costs $80+/mo. Prism costs about $0.20/mo in blob storage. The tradeoff is that you self-host and don't get Sentry's full feature set (performance monitoring, session replay, etc.), but for pure error tracking, Prism covers the essential workflow.

### Data Flow

```
+-------------------+       +-------------------+       +-------------------+
|   SDKs            |       |   Ingestion API   |       |   Blob Storage    |
|   (Laravel,       | ----> |   POST /api/v1/   | ----> |   (Azure Blob /   |
|    Next.js,       |  HTTP |   events          |       |    S3 / local)    |
|    NestJS)        |       |                   |       |                   |
|                   |       |   - Validate      |       |   NDJSON.gz files |
|   fire-and-forget |       |   - Fingerprint   |       |   partitioned by  |
|   batched         |       |   - Enrich        |       |   time/project    |
|   fail-silent     |       |                   |       +-------------------+
+-------------------+       +--------+----------+
                                     |
                    +----------------+----------------+
                    |                                 |
                    v                                 v
           +-------------------+             +-------------------+
           |   Postgres        |             |   Redis Pub/Sub   |
           |   (Hot Index)     |             |   (Real-Time)     |
           |                   |             |                   |
           |   issues table:   |             |   Lightweight     |
           |   fingerprint,    |             |   signal:         |
           |   count,          |             |   { project_id,   |
           |   first_seen,     |             |     fingerprint,  |
           |   last_seen,      |             |     level,        |
           |   level, status   |             |     message }     |
           +-------------------+             +--------+----------+
                                                      |
                                                      v
                                             +-------------------+
                                             |   Ripple          |
                                             |   WebSocket       |
                                             |                   |
                                             |   Dashboard SPA   |
                                             |   CLI TUI         |
                                             +-------------------+
```

### Postgres as Cache, Not Truth

Postgres is NOT the source of truth -- blob storage is. Postgres holds only the **issues table**: a deduplicated, aggregated view of errors. If you lose the Postgres database, you can rebuild it by scanning blob storage. If you lose blob storage, you lose the raw event details but still have the issue summaries.

The issues table is intentionally tiny:

```sql
CREATE TABLE issues (
    id            BIGSERIAL PRIMARY KEY,
    project_id    VARCHAR(32) NOT NULL,
    fingerprint   VARCHAR(64) NOT NULL,  -- SHA-256 of normalized stacktrace
    level         VARCHAR(16) NOT NULL,  -- error, warning, fatal
    title         TEXT NOT NULL,         -- exception class + first line of message
    culprit       TEXT,                  -- file:line of top stack frame
    platform      VARCHAR(32) NOT NULL,
    first_seen    TIMESTAMPTZ NOT NULL,
    last_seen     TIMESTAMPTZ NOT NULL,
    event_count   BIGINT DEFAULT 1,
    status        VARCHAR(16) DEFAULT 'unresolved',  -- unresolved, resolved, ignored
    environment   VARCHAR(64),
    release       VARCHAR(128),
    UNIQUE (project_id, fingerprint)
);
```

This table stays small (one row per unique error, not per event) and fast. A project with 100,000 events might have only 500 unique issues.

---

## The Event Contract

Every error event, regardless of platform, conforms to a single contract:

```json
{
    "event_id": "550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2026-03-22T14:30:00.123Z",
    "project_id": "proj_abc123",
    "environment": "production",
    "platform": "php",
    "level": "error",
    "release": "v2.3.1",
    "server_name": "web-01",
    "transaction": "POST /api/orders",
    "exception": {
        "type": "App\\Exceptions\\PaymentFailedException",
        "value": "Payment gateway returned HTTP 502",
        "stacktrace": [
            {
                "file": "/app/src/Services/PaymentService.php",
                "line": 142,
                "function": "charge",
                "class": "App\\Services\\PaymentService",
                "context": {
                    "pre": ["    $response = $this->gateway->post('/charge', ["],
                    "line": "    throw new PaymentFailedException($response->body());",
                    "post": ["    }"]
                }
            }
        ]
    },
    "context": {
        "request": {
            "url": "https://example.com/api/orders",
            "method": "POST",
            "headers": { "Content-Type": "application/json" }
        },
        "user": {
            "id": 42,
            "email": "user@example.com"
        },
        "runtime": {
            "name": "php",
            "version": "8.4.0"
        }
    },
    "tags": {
        "tenant_id": "tenant_xyz",
        "payment_provider": "stripe"
    },
    "breadcrumbs": [
        {
            "timestamp": "2026-03-22T14:29:59.800Z",
            "category": "http",
            "message": "POST https://api.stripe.com/v1/charges",
            "level": "info",
            "data": { "status_code": 502 }
        }
    ]
}
```

### Fingerprinting for Issue Deduplication

The fingerprinting engine determines whether two error events represent the "same" issue:

1. **Extract** the exception type and stacktrace frames
2. **Normalize** each frame: strip line numbers (they change between releases), strip absolute paths (keep relative), keep function/method names and class names
3. **Concatenate** the normalized frames into a single string
4. **Hash** with SHA-256 to produce a 64-character fingerprint

Two events with the same exception type thrown from the same call chain (regardless of line number shifts between deployments) produce the same fingerprint and are grouped into the same issue. The fingerprint is deterministic and platform-agnostic.

Custom fingerprinting rules can override the default: SDKs can set `fingerprint: ["custom-group-key"]` to force grouping.

---

## SDK Design Philosophy

All Prism SDKs follow three principles:

1. **Fire-and-forget**: The SDK captures the error and returns immediately. The actual HTTP request to the ingestion API happens asynchronously (background thread, event loop callback, or queue).

2. **Batched**: Events are buffered in memory and flushed in batches (configurable, default every 5 seconds or 10 events, whichever comes first). This reduces HTTP overhead and connection count.

3. **Fail-silent**: If the ingestion API is unreachable or returns an error, the SDK logs a debug message and discards the event. It never throws, never blocks, never degrades application performance. Error reporting should not cause errors.

### PII Redaction

All SDKs include a configurable PII scrubber that runs before events leave the application:

- Redact passwords, tokens, API keys from request headers and body
- Redact credit card numbers (pattern matching)
- Configurable field allowlist/blocklist
- Scrub `Authorization` and `Cookie` headers by default

---

## Multi-Project Support

Prism supports multiple projects within a single installation. Each project has:

- A unique `project_id` and display name
- One or more API keys (for SDK authentication)
- Separate blob storage partitions (naturally isolated by the path structure)
- Separate issue tracking in Postgres (filtered by `project_id`)
- Separate dashboard views

This makes Prism suitable for teams managing multiple applications (monorepo or separate repos) from a single error reporting instance.

---

## Dependencies

| Package          | Purpose                                                  |
|------------------|----------------------------------------------------------|
| `lattice/http`   | Ingestion API endpoint, Web SPA serving                  |
| `lattice/database`     | Postgres connection for issues table                     |
| `lattice/cache`  | Redis connection for pub/sub real-time signals           |
| `lattice/ripple` | WebSocket broadcasting to dashboard and CLI              |
| `lattice/module` | `#[Module]` attribute for `PrismModule` registration     |

### Optional

| Package                      | Purpose                                   |
|------------------------------|-------------------------------------------|
| `azure/storage-blob`         | Azure Blob Storage adapter                |
| `aws/aws-sdk-php` (S3 only) | S3-compatible storage adapter             |
| `lattice/mail`               | Email alerting on new issues              |

---

## Design Inspiration

### Sentry
- Issue-based error grouping (fingerprinting)
- Stacktrace viewer with source context
- Issue lifecycle: unresolved, resolved, ignored
- Release tracking
- SDK design: fire-and-forget, breadcrumbs, context enrichment

### BugSnag
- Lightweight SDKs with automatic framework integration
- Error grouping by normalized stacktrace

### Key Design Principles
- **Blob storage is the source of truth**: Raw events live in cheap, durable, append-only files. Postgres is a derived index that can be rebuilt.
- **Near-zero cost**: For most teams, Prism's running cost is effectively free. No per-event pricing, no vendor lock-in.
- **SDK safety**: An error in error reporting is unacceptable. SDKs must be the most reliable code in the application.
- **Simplicity**: Prism does one thing -- error tracking. No performance monitoring, no session replay, no profiling. Do error tracking well.
- **Self-hosted first**: Prism is designed to run on your own infrastructure, not as a managed service. Everything is a LatticePHP module you install and configure.
