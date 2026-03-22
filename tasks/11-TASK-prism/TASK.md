# 11 — Prism (Self-Hosted Error Reporting)

> Build a self-hosted error reporting platform: ErrorEvent contract, blob storage, Postgres index, ingestion API, multi-platform SDKs, Next.js SPA dashboard, real-time feed via WebSocket, CLI TUI, alerting, and source maps

## Dependencies
- Wave 1-3 packages complete
- Packages: `packages/http/`, `packages/database/`, `packages/cache/`, `packages/ripple/`, `packages/module/`
- Optional: `azure/storage-blob`, `aws/aws-sdk-php`, `lattice/mail`
- Frontend: Next.js + NextUI + TailwindCSS + TanStack Query + Zustand + Zod

## Subtasks

### 1. [ ] Core — ErrorEvent contract, fingerprinting engine, storage adapters (local, S3, Azure Blob)

#### ErrorEvent Contract
- Define `ErrorEvent` value object with all fields: event_id (UUID v4), timestamp (ISO 8601), project_id, environment, platform, level (error/warning/fatal/info), release, server_name, transaction
- Define `ExceptionData` value object: type, value (message), stacktrace (array of `StackFrame`)
- Define `StackFrame` value object: file, line, function, class, module, context (pre/line/post source lines), column (optional for JS)
- Define `ContextData` value object: request (url, method, headers, query, body), user (id, email, username, ip), runtime (name, version), OS (name, version), device (optional), custom key-value pairs
- Define `Breadcrumb` value object: timestamp, category (http, query, log, navigation, user), message, level, data
- Define `TagSet` as string key-value map with validation (max key 32 chars, max value 200 chars, max 50 tags)
- Implement `ErrorEvent::fromArray()` and `ErrorEvent::toArray()` for serialization round-trips
- Implement validation: required fields, valid UUID format, valid ISO 8601 timestamp, valid level enum
- Generate `event_id` server-side if not provided, enrich `timestamp` with server receive time if missing
- Unit tests for construction, serialization/deserialization round-trip, and validation rejection

#### Fingerprinting Engine
- Create `FingerprintGenerator` class with `generate(ErrorEvent $event): string` producing SHA-256 fingerprint
- Normalization: extract exception type + stacktrace frames, strip absolute paths to relative, strip line/column numbers, keep function/method/class names, remove vendor/library frames (configurable vendor path prefixes: `vendor/`, `node_modules/`)
- Concatenation format: `{exception_type}|{frame1_class}:{frame1_function}|{frame2_class}:{frame2_function}|...`
- Hashing: SHA-256 the concatenated string, return as 64-character hex string
- Support custom fingerprinting: if event includes `fingerprint` field (array of strings), use those instead
- Handle events with no stacktrace (fingerprint from type + message), handle empty exception (level + transaction + message)
- Platform-specific normalizers for PHP paths, JavaScript paths (webpack, source maps), Node.js paths
- Unit tests: same error with different line numbers produces same fingerprint, JS fingerprinting, custom override, vendor exclusion

#### Blob Storage Adapters
- Define `BlobStorageAdapterInterface` with methods: `write()`, `append()`, `read()`, `exists()`, `list()`, `delete()`
- Path layout: `{year}/{month}/{day}/{hour}/{project_id}/{environment}/{platform}/events.ndjson.gz`
- Create `EventWriter` class: buffer events in memory, flush on size threshold (default 1 MB) or time threshold (default 60 seconds), GZIP compress NDJSON on flush, handle concurrent writes safely (mutex), flush remaining on shutdown
- Create `EventReader` class: decompress GZIP, parse NDJSON line by line yielding `ErrorEvent` objects, filter by fingerprint for issue-specific reads
- Implement `LocalFilesystemAdapter` using PHP filesystem functions with `LOCK_EX`, auto-create directories
- Implement `S3Adapter` using AWS SDK for PHP with `PutObject`, `GetObject`, `HeadObject`, `ListObjectsV2`, `DeleteObject`; support S3-compatible providers (MinIO, DigitalOcean Spaces)
- Implement `AzureBlobAdapter` using Azure Blob Storage SDK with append blob for true append; support storage tiers (hot/cool/archive)
- Create `PrismServiceProvider` and `PrismModule` with `#[Module]` attribute; bind storage adapter from config, register repositories, routes, CLI commands; publish `config/prism.php`
- Unit tests for EventWriter buffering/flush, EventReader parsing, path generation, each adapter CRUD ops (mock clients)

#### Verification
- [ ] `ErrorEvent::fromArray()` correctly constructs from JSON and `toArray()` round-trips losslessly
- [ ] Fingerprinting produces same hash for same error across different line numbers
- [ ] EventWriter buffers events and flushes to blob storage as compressed NDJSON
- [ ] All three storage adapters pass CRUD operation tests

### 2. [ ] Postgres schema — projects, issues, events_index tables
- Create migration: `projects` table — id, name, slug, api_key_hash, created_at, updated_at
- Create migration: `issues` table — id, project_id (FK), fingerprint (varchar 64), level, title (exception type + message), culprit (file:line), platform, environment, release, first_seen (timestamptz), last_seen (timestamptz), event_count (bigint), status (enum: unresolved/resolved/ignored), created_at, updated_at
- Add unique index on `issues (project_id, fingerprint)`
- Add index on `issues (project_id, status, last_seen DESC)` for default list query
- Add index on `issues (project_id, level)` for level filtering
- Create migration: `events_index` table — id, issue_id (FK), event_id (UUID), blob_path (text), byte_offset (bigint), timestamp (timestamptz)
- Add index on `events_index (issue_id, timestamp DESC)` and `events_index (event_id)`
- Create `IssueRepository` with methods: `findOrCreate(projectId, fingerprint, event)` (upsert), `incrementCount(issueId)` (atomic), `updateLastSeen(issueId, timestamp)`, `findById(id)`, `listByProject(projectId, filters)` (paginated, filterable by status/level/environment/platform, searchable, sortable), `updateStatus(issueId, status)`
- Create `EventIndexRepository`: `insert(issueId, eventId, blobPath, timestamp)`, `findByIssue(issueId, limit, offset)`, `findByEventId(eventId)`
- Unit tests for upsert logic, listing with filters, event index queries

#### Verification
- [ ] Migrations create all tables with correct indexes
- [ ] `IssueRepository::findOrCreate()` creates new issue or returns existing by fingerprint
- [ ] `IssueRepository::listByProject()` supports filtering, searching, sorting, and pagination
- [ ] `EventIndexRepository` correctly maps events to issues and blob paths

### 3. [ ] Ingestion API — POST /api/v1/events, auth, rate limiting, batch
- Accept JSON body: single `ErrorEvent` or batch (array of events)
- Parse and validate each event against the ErrorEvent contract; reject invalid with 422 and structured errors
- Return 202 Accepted with `{ "event_ids": ["..."] }`
- Handle `Content-Encoding: gzip` for compressed request bodies; enforce max request size (configurable, default 1 MB)
- API key authentication: each project has API keys stored as SHA-256 hashes; authenticate via `X-Prism-Key` or `Authorization: Bearer <key>`; reject 401/403 for missing/invalid keys
- Rate limiting: token bucket per project (configurable, default 1000 events/minute), return 429 with `Retry-After` header, track in Redis
- Batch ingestion: validate each event independently, return partial success: `{ "accepted": [...], "rejected": [{ "index": N, "error": "..." }] }`; limit batch size (default 100)
- After validation: pass event to `EventWriter` for blob storage, record blob path in `events_index`
- Generate fingerprint, call `IssueRepository::findOrCreate()`, `incrementCount()`, `updateLastSeen()`
- Regression detection: if issue was `resolved` and new event arrives, set back to `unresolved`
- Publish lightweight signal to Redis pub/sub channel `prism:events:{project_id}` with event_id, issue_id, fingerprint, level, title, is_new_issue, is_regression (do NOT include full event)
- If Redis unavailable, log warning and continue
- Project management API: `POST /api/v1/projects` (create), `GET /api/v1/projects` (list), `GET /api/v1/projects/:id` (detail), `POST /api/v1/projects/:id/rotate-key` (rotate API key), `DELETE /api/v1/projects/:id`
- Unit tests for ingestion (single, batch, validation, gzip), auth (valid/invalid keys), rate limiting, regression detection, signal publishing, project CRUD, key rotation

#### Verification
- [ ] Valid event returns 202 with event_id confirmation
- [ ] Invalid event returns 422 with structured validation errors
- [ ] Unauthenticated request returns 401, invalid key returns 403
- [ ] Rate limit returns 429 with `Retry-After` header when exceeded
- [ ] Batch ingestion returns partial success with accepted/rejected lists
- [ ] Resolved issue reverts to unresolved on new event (regression)
- [ ] Redis pub/sub signal is published after processing

### 4. [ ] SDK — sdk-core (shared contract), sdk-laravel (PHP)

#### sdk-core
- Define ErrorEvent JSON schema as shared specification document
- Implement fingerprinting algorithm (matching server logic, for client-side grouping hints)
- Implement PII sanitizer: configurable field patterns to redact (Authorization, Cookie, password, token, secret, credit_card headers/fields; Luhn-valid card numbers; SSN patterns), configurable allowlist/blocklist
- Implement breadcrumb collector: in-memory ring buffer (configurable size, default 100)
- Implement event builder: fluent API for constructing ErrorEvent with all optional fields
- Implement transport abstraction and batch transport (buffer events, flush on interval or count threshold)

#### sdk-laravel (`lattice/prism-laravel`)
- Composer package `lattice/prism-laravel` with `PrismServiceProvider` for auto-discovery
- Laravel exception handler integration: register reporting callback that captures exceptions
- Capture full stacktrace with source context (N lines before/after), HTTP request context (sanitized), authenticated user context, Laravel-specific context (route name, middleware, controller/action), PHP runtime context
- Breadcrumb integration: database queries, HTTP client requests, log messages, cache operations
- Async dispatch via Laravel queue (configurable, fallback to synchronous)
- Configuration: DSN/API key, environment, release (auto-detect from git), sample rate (0.0-1.0), ignored exceptions list
- Tag support: `Prism::setTag()`, context support: `Prism::setContext()`, manual capture: `Prism::captureException()`, `Prism::captureMessage()`
- Unit tests for exception capture, PII sanitization, breadcrumb collection, async dispatch
- Integration test: throw exception in Laravel app -> event arrives at ingestion API

#### Verification
- [ ] PII sanitizer redacts sensitive fields (Authorization, Cookie, password, tokens)
- [ ] sdk-laravel captures exception with full stacktrace and request context
- [ ] Breadcrumbs include database queries and HTTP client requests
- [ ] Events are dispatched asynchronously via queue when configured

### 5. [ ] SDK — sdk-next (JS browser+Node), sdk-nest (NestJS)

#### sdk-next (`@lattice/prism-next`)
- npm package `@lattice/prism-next`
- Browser: `window.onerror` handler, `unhandledrejection` listener, source context (file URL, line/column), request context (location, userAgent, viewport), breadcrumbs (fetch/XHR interception, console.error, DOM clicks, navigation)
- `sendBeacon` transport for page unload, batched transport for normal operation
- Node.js SSR: `uncaughtException`/`unhandledRejection` handlers, API route middleware wrapper, request context
- Next.js integration: `withPrism()` wrapper for `next.config.js`, `<PrismErrorBoundary>` React component
- Source map upload CLI: `prism sourcemaps upload --release=v2.3.1 --path=.next/`
- Tree-shakeable, browser bundle < 5 KB gzipped
- Configuration: DSN, environment, release, sample rate, ignored errors
- Unit tests for browser and Node.js error capture, breadcrumbs, sendBeacon

#### sdk-nest (`@lattice/prism-nest`)
- npm package `@lattice/prism-nest`
- NestJS global exception filter (`@Catch()`) capturing all unhandled exceptions
- HTTP request context, NestJS-specific context (controller, handler, route, guards, interceptors)
- Correlation ID tracking via `X-Correlation-ID` header
- Breadcrumbs: Axios interceptor, TypeORM subscriber, log messages
- Async non-blocking transport, `PrismModule` for NestJS DI
- Manual capture: `PrismService.captureException()`, `PrismService.captureMessage()`
- `@CaptureErrors()` method decorator
- Unit tests for exception filter, correlation ID, breadcrumbs
- Integration test: throw exception in NestJS handler -> event arrives at ingestion API

#### Verification
- [ ] Browser SDK captures `window.onerror` and `unhandledrejection` events
- [ ] `sendBeacon` transport sends events reliably on page unload
- [ ] `<PrismErrorBoundary>` captures React render errors
- [ ] NestJS SDK captures exceptions with controller and handler context
- [ ] Correlation ID is extracted and attached to all events

### 6. [ ] Frontend — Next.js SPA: projects, issues list, issue detail (stacktrace viewer), live feed

**Frontend stack:** Next.js + NextUI + TailwindCSS + TanStack Query + Zustand + Zod. Component-by-component, feature-by-feature. SOLID + DRY. Each dashboard view is a switchable module.

#### Project Scaffolding
- Initialize Next.js + TypeScript project with TailwindCSS (dark default theme via NextUI)
- Configure path aliases (`@/components`, `@/api`, `@/hooks`, `@/stores`, `@/pages`)
- Set up ESLint + Prettier, Vitest for unit tests
- Create typed API client module with TanStack Query hooks for all endpoints
- Create Zustand stores: `useProjectStore`, `useFilterStore`, `useWebSocketStore`
- Create WebSocket connection manager (Ripple) for real-time updates
- Validate all API responses with Zod schemas

#### Layout Shell
- Sidebar navigation: Projects, Issues, Live Feed, Settings — with active page indicator
- Header: project selector dropdown (Zustand state), environment filter, global search
- Breadcrumbs component, loading skeleton states, error boundary with retry

#### Projects List Page
- List projects with name, platform, error count (24h), last error timestamp
- Create project button with NextUI modal (name, platform)
- Project settings: rename, rotate API key (with copy), delete with confirmation
- API key setup instructions per platform (copy-paste SDK install commands)
- Empty state for no projects

#### Issues List Page
- Paginated table with TanStack Query: status icon, level badge, title (exception class + message), event count, last seen, environment
- Sort by: last seen (default), first seen, event count
- Filter by: status, level, environment, platform (Zustand filter store)
- Full-text search with 300ms debounce
- Bulk actions: select multiple, resolve all, ignore all
- Click row to navigate to issue detail
- Real-time: new issues appear with highlight animation, existing counts update via WebSocket
- Regression badge, empty state, pagination controls

#### Issue Detail Page
- Header: exception type, message, status badge, level badge, event count, first/last seen
- Action buttons: Resolve, Ignore, Unresolve (optimistic UI with revert on failure)
- Stacktrace viewer: collapsible frames, each showing file path, line, function/method
  - Expandable source context with syntax-highlighted code snippet
  - Distinguish app frames from vendor frames (collapse vendor by default, "In app" toggle)
- Sample events section: list recent events, click to view full context (request, user, tags, breadcrumbs)
- Tags section, trend chart (event count per hour/day for 7 days), environment breakdown, release breakdown
- Breadcrumbs viewer (chronological), context viewer (request, user, runtime)
- Copy issue URL to clipboard

#### Live Feed Page
- Real-time stream via Ripple WebSocket
- Each entry: timestamp, level badge, exception type, message, environment, project
- Auto-scroll with pause button, filter by level/environment/project
- Click entry to navigate to issue detail
- Connection status indicator (green/yellow/red), sound notification toggle
- Max displayed entries (default 500, remove oldest from DOM)

#### Issue Actions
- Resolve, Ignore, Unresolve with optimistic UI update
- Confirmation dialog for bulk actions, toast notifications for feedback

#### Verification
- [ ] Projects list page renders with create/settings/delete functionality
- [ ] Issues list page supports filtering, sorting, search, bulk actions, and pagination
- [ ] Issue detail page displays stacktrace with collapsible frames and source context
- [ ] Live feed page streams errors in real-time with auto-scroll and filtering
- [ ] WebSocket updates issue counts and adds new issues in real-time

### 7. [ ] Real-time — Redis pub/sub to Ripple WebSocket to dashboard
- Subscribe to Redis channel `prism:events:{project_id}` for each active project
- On receiving signal, broadcast via Ripple WebSocket to connected dashboard clients
- Signal contains: event_id, issue_id, fingerprint, level, title, is_new_issue, is_regression
- Dashboard WebSocket handler: on new issue signal, prepend to issues list; on existing issue signal, increment count and update last_seen
- Live feed page: append new entries to the stream
- Handle Redis disconnection gracefully (reconnect with backoff)
- Handle WebSocket client disconnection (clean up subscriptions)
- Unit tests for signal handling, broadcast logic, and reconnection

#### Verification
- [ ] Signal published to Redis after event ingestion reaches dashboard via WebSocket
- [ ] New issue appears in issues list and live feed in real-time
- [ ] Existing issue count increments live without page refresh
- [ ] Redis disconnection triggers reconnection without data loss

### 8. [ ] CLI TUI — `php lattice prism` interactive + non-interactive commands

#### Interactive TUI (`php lattice prism`)
- Dashboard view: project selector, top errors (last 24h), live error feed, stats bar (total errors today, unresolved issues, new issues today)
- Issue list view: scrollable table with color-coded levels/statuses, arrow key navigation, `/` to search, `Enter` to open detail, filter keys (`u`/`r`/`i`/`a`)
- Issue detail view: exception type, message, stacktrace with syntax highlighting (file paths cyan, line numbers yellow), action keys (`R` resolve, `I` ignore, `U` unresolve)
- Keyboard shortcuts: `q` quit, `r` refresh, `Tab` switch views, `?` help

#### Non-Interactive CLI Commands
- `php lattice prism:issues` — formatted table with `--project`, `--status`, `--level`, `--limit`, `--json` flags
- `php lattice prism:issue <id>` — issue detail with stacktrace, `--json` flag
- `php lattice prism:live` — live error stream (tail mode) with `--project`, `--level` filters, color-coded, Ctrl+C to exit
- `php lattice prism:projects` — list projects with error counts, `--json` flag
- `php lattice prism:stats` — error counts and trends (24h, 7d, 30d), top 5 errors, `--project`, `--json` flags
- Unit tests for all CLI commands including `--json` output format

#### Verification
- [ ] Interactive TUI displays dashboard with project selector and live feed
- [ ] Issue list view supports navigation, search, and filtering
- [ ] Non-interactive commands output correct data in both table and JSON formats
- [ ] `prism:live` streams errors in real-time with color coding

### 9. [ ] Polish — alerting (Slack/email/webhook), source maps, retention, docs + tests

#### Alerting
- Define alert rules in `prism.alerting.rules` config: `new_issue`, `regression`, `threshold` (N events in M minutes), `spike` (N% increase over baseline)
- Alert channels: Slack webhook (formatted message with issue title, count, link), email via `lattice/mail`, generic webhook (POST JSON)
- Alert cooldown: do not re-alert for same issue within N minutes (configurable, default 60)
- Alert history in Postgres, dashboard alert panel
- Unit tests for rule evaluation, cooldown logic; integration test for Slack webhook

#### Source Map Support
- Source map upload endpoint: `POST /api/v1/sourcemaps` keyed by release version
- Store in blob storage: `sourcemaps/{project_id}/{release}/{file.map}`
- On JS event ingestion, resolve minified stacktrace frames using uploaded source maps
- Source map validation, automatic cleanup after N releases
- CLI: `php lattice prism:sourcemaps:upload --project=<id> --release=v1.0 --path=./dist/`
- Unit tests for source map resolution and minified -> original mapping

#### Retention Policies
- Configurable retention per project: keep raw events for N days (default 90)
- Auto-archive to cool/archive storage tier; auto-delete after N days (default 365)
- Clean up `events_index` rows for deleted blobs; retain issue summaries indefinitely
- CLI: `php lattice prism:cleanup`; scheduled task: daily via cron
- Unit tests for retention policy enforcement and storage tier migration

#### Documentation
- Installation guide, quick start (first error in 5 minutes), SDK installation per platform
- Event contract reference, fingerprinting explanation, storage architecture
- API reference, alerting configuration, source map setup, CLI TUI usage
- Self-hosting guide, migration guide from Sentry/BugSnag

#### Tests
- Unit tests: FingerprintGenerator (all platforms), EventWriter/Reader, all storage adapters, repositories, ingestion API, project API, alert rules, PII sanitizer
- Integration tests: SDK -> ingestion -> issue created -> event in blob -> Redis signal; regression detection; source map resolution; retention cleanup
- Performance tests: 10,000 events/minute ingestion; 100,000 issues list query
- E2E frontend tests: issues list renders and filters, issue detail stacktrace and actions, live feed streaming, project creation

#### Verification
- [ ] Alert fires to Slack when a new issue appears
- [ ] Source map resolves minified JS stacktrace to original source
- [ ] Retention policy deletes old blobs and cleans up event index
- [ ] All unit, integration, performance, and E2E tests pass

## Integration Verification
- [ ] SDK sends error -> ingestion stores in blob -> issue appears in Postgres -> SPA shows stacktrace with source context
- [ ] Live feed updates in real-time when new error arrives via WebSocket
- [ ] Resolved issue reverts to unresolved when regression event arrives
- [ ] Source map upload -> JS error -> resolved stacktrace displayed in dashboard
- [ ] CLI TUI displays issues and streams live errors
- [ ] Alert fires to Slack/email/webhook on new issue
- [ ] Retention cleanup removes old events and index entries
