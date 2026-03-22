# Prism -- Task List

---

## Phase 1 -- Core & Storage

### ErrorEvent Contract

- [ ] Define `ErrorEvent` value object with all contract fields: event_id (UUID v4), timestamp (ISO 8601), project_id, environment, platform, level (error/warning/fatal/info), release, server_name, transaction
- [ ] Define `ExceptionData` value object: type (exception class), value (message), stacktrace (array of `StackFrame`)
- [ ] Define `StackFrame` value object: file, line, function, class, module, context (pre/line/post source lines), column (optional, for JS)
- [ ] Define `ContextData` value object: request (url, method, headers, query, body), user (id, email, username, ip), runtime (name, version), OS (name, version), device (optional, for browser), custom key-value pairs
- [ ] Define `Breadcrumb` value object: timestamp, category (http, query, log, navigation, user), message, level, data (key-value)
- [ ] Define `TagSet` as a string key-value map with validation (max key length 32, max value length 200, max 50 tags)
- [ ] Implement `ErrorEvent::fromArray(array $data): self` factory method for deserialization from JSON
- [ ] Implement `ErrorEvent::toArray(): array` for serialization to JSON (for storage and API responses)
- [ ] Implement validation: required fields (event_id, timestamp, project_id, platform, level, exception), valid UUID format, valid ISO 8601 timestamp, valid level enum
- [ ] Generate `event_id` on the server side if not provided by the SDK
- [ ] Enrich `timestamp` with server receive time if not provided
- [ ] Unit tests for ErrorEvent construction and validation
- [ ] Unit tests for serialization/deserialization round-trip
- [ ] Unit tests for validation rejection of invalid events

### Fingerprinting Engine

- [ ] Create `FingerprintGenerator` class
- [ ] Implement `generate(ErrorEvent $event): string` -- produce a SHA-256 fingerprint for issue deduplication
- [ ] Normalization step 1: extract exception type and stacktrace frames
- [ ] Normalization step 2: strip absolute file paths -- keep only the path relative to a detected project root (e.g., `/app/src/Services/Foo.php` -> `src/Services/Foo.php`)
- [ ] Normalization step 3: strip line numbers from all frames (they shift between releases)
- [ ] Normalization step 4: strip column numbers
- [ ] Normalization step 5: keep function/method names, class names, and module names
- [ ] Normalization step 6: remove vendor/library frames from the fingerprint (configurable list of vendor path prefixes: `vendor/`, `node_modules/`, internal PHP/Node frames)
- [ ] Concatenation: join normalized frames into a single string: `{exception_type}|{frame1_class}:{frame1_function}|{frame2_class}:{frame2_function}|...`
- [ ] Hashing: SHA-256 the concatenated string, return as 64-character hex string
- [ ] Support custom fingerprinting: if the event includes a `fingerprint` field (array of strings), use those strings instead of the default stacktrace-based fingerprint
- [ ] Handle events with no stacktrace: fingerprint from exception type + message (normalized)
- [ ] Handle events with empty exception: fingerprint from level + transaction + message
- [ ] Platform-specific normalizers: PHP paths (`/app/src/...`), JavaScript paths (`webpack:///src/...`, source map paths), Node.js paths
- [ ] Unit tests for PHP stacktrace fingerprinting (same error, different line numbers -> same fingerprint)
- [ ] Unit tests for JavaScript stacktrace fingerprinting
- [ ] Unit tests for custom fingerprint override
- [ ] Unit tests for events with no stacktrace
- [ ] Unit tests for vendor frame exclusion

### BlobStorageAdapter (Core Interface)

- [ ] Define `BlobStorageAdapterInterface` with methods: `write(string $path, string $data): void`, `append(string $path, string $data): void`, `read(string $path): string`, `exists(string $path): bool`, `list(string $prefix): array`, `delete(string $path): void`
- [ ] Define path generation logic: `{year}/{month}/{day}/{hour}/{project_id}/{environment}/{platform}/events.ndjson.gz`
- [ ] Create `EventWriter` class that buffers events in memory and flushes to blob storage
- [ ] EventWriter: accept `ErrorEvent`, serialize to JSON, append newline, add to in-memory buffer
- [ ] EventWriter: flush buffer when size threshold reached (configurable, default 1 MB) or time threshold (configurable, default 60 seconds)
- [ ] EventWriter: on flush, GZIP compress the buffered NDJSON, write to blob storage at the computed path
- [ ] EventWriter: handle concurrent writes safely (mutex/lock for the in-memory buffer)
- [ ] EventWriter: flush all remaining buffered events on graceful shutdown
- [ ] Create `EventReader` class that reads events from blob storage
- [ ] EventReader: `readEvents(string $blobPath): iterable<ErrorEvent>` -- decompress GZIP, parse NDJSON line by line, yield ErrorEvent objects
- [ ] EventReader: `readEventsForIssue(string $fingerprint, string $projectId, DateTimeInterface $from, DateTimeInterface $to): iterable<ErrorEvent>` -- scan relevant blobs, filter by fingerprint
- [ ] Unit tests for EventWriter buffering and flush logic
- [ ] Unit tests for EventReader NDJSON parsing
- [ ] Unit tests for path generation logic

### LocalFilesystemAdapter

- [ ] Implement `BlobStorageAdapterInterface` using PHP filesystem functions
- [ ] `write()`: `file_put_contents` with `LOCK_EX`
- [ ] `append()`: `file_put_contents` with `FILE_APPEND | LOCK_EX`
- [ ] `read()`: `file_get_contents`
- [ ] `exists()`: `file_exists`
- [ ] `list()`: recursive directory scan with glob matching
- [ ] `delete()`: `unlink`
- [ ] Auto-create directory structure on write (recursive `mkdir`)
- [ ] Configurable base path (default: `storage/prism/events/`)
- [ ] Unit tests for all operations
- [ ] Unit tests for directory auto-creation

### S3Adapter

- [ ] Implement `BlobStorageAdapterInterface` using AWS SDK for PHP (`aws/aws-sdk-php`)
- [ ] `write()`: `PutObject` with `ContentEncoding: gzip`
- [ ] `append()`: download existing, append, re-upload (S3 doesn't support true append) -- or use multipart upload
- [ ] `read()`: `GetObject`, decompress
- [ ] `exists()`: `HeadObject`
- [ ] `list()`: `ListObjectsV2` with prefix
- [ ] `delete()`: `DeleteObject`
- [ ] Configuration: bucket, region, access key, secret key (or IAM role)
- [ ] Support S3-compatible providers (MinIO, DigitalOcean Spaces, Backblaze B2) via custom endpoint
- [ ] Unit tests with mock S3 client

### AzureBlobAdapter

- [ ] Implement `BlobStorageAdapterInterface` using Azure Blob Storage SDK
- [ ] `write()`: upload block blob with `Content-Encoding: gzip`
- [ ] `append()`: use Azure Append Blob type for true append (no re-upload needed)
- [ ] `read()`: download blob, decompress
- [ ] `exists()`: get blob properties
- [ ] `list()`: list blobs with prefix
- [ ] `delete()`: delete blob
- [ ] Configuration: connection string, container name, access key
- [ ] Support storage tiers: hot (default for current hour), cool (for older data), archive (for retention)
- [ ] Unit tests with mock Azure client

### Postgres Schema

- [ ] Create migration: `projects` table -- id, name, slug, api_key_hash, created_at, updated_at
- [ ] Create migration: `issues` table -- id, project_id (FK), fingerprint (varchar 64), level, title (exception type + message), culprit (file:line), platform, environment, release, first_seen (timestamptz), last_seen (timestamptz), event_count (bigint), status (enum: unresolved/resolved/ignored), created_at, updated_at
- [ ] Add unique index on `issues (project_id, fingerprint)`
- [ ] Add index on `issues (project_id, status, last_seen DESC)` for the default issues list query
- [ ] Add index on `issues (project_id, level)` for level filtering
- [ ] Create migration: `events_index` table -- id, issue_id (FK), event_id (UUID), blob_path (text), byte_offset (bigint, optional), timestamp (timestamptz)
- [ ] Add index on `events_index (issue_id, timestamp DESC)` for fetching sample events for an issue
- [ ] Add index on `events_index (event_id)` for direct event lookup
- [ ] Create `IssueRepository` class with methods: `findOrCreate(projectId, fingerprint, event)`, `incrementCount(issueId)`, `updateLastSeen(issueId, timestamp)`, `findById(id)`, `listByProject(projectId, filters)`, `updateStatus(issueId, status)`
- [ ] `findOrCreate`: upsert -- if issue with (project_id, fingerprint) exists, return it; otherwise create with initial data from the event
- [ ] `incrementCount`: atomic increment (`UPDATE issues SET event_count = event_count + 1, last_seen = :now WHERE id = :id`)
- [ ] `listByProject`: paginated, filterable by status, level, environment, platform, search (title ILIKE), sortable by last_seen, event_count, first_seen
- [ ] Create `EventIndexRepository` class: `insert(issueId, eventId, blobPath, timestamp)`, `findByIssue(issueId, limit, offset)`, `findByEventId(eventId)`
- [ ] Unit tests for IssueRepository upsert logic
- [ ] Unit tests for IssueRepository listing with filters
- [ ] Unit tests for EventIndexRepository

### PrismServiceProvider and PrismModule

- [ ] Create `PrismServiceProvider` -- registers configuration, binds storage adapter, repositories, fingerprint generator
- [ ] Create `PrismModule` with `#[Module]` attribute -- registers service provider, API routes, web routes, CLI commands
- [ ] Bind `BlobStorageAdapterInterface` to configured implementation (local/s3/azure based on config)
- [ ] Register ingestion API routes
- [ ] Register web SPA routes
- [ ] Register CLI commands
- [ ] Publish `config/prism.php` configuration file
- [ ] Configuration: storage driver, storage path/bucket, Postgres connection, Redis connection, projects, auth settings
- [ ] Unit tests for service provider registration
- [ ] Unit tests for configuration binding

---

## Phase 2 -- Ingestion API

### POST /api/v1/events -- Main Ingestion Endpoint

- [ ] Accept JSON body: single ErrorEvent or batch (array of ErrorEvent)
- [ ] Parse and validate each event against the ErrorEvent contract
- [ ] Reject invalid events with 422 and structured error response listing validation failures
- [ ] Return 202 Accepted immediately (processing happens asynchronously if queued, or synchronously for simple deployments)
- [ ] Response body: `{ "event_ids": ["..."] }` confirming receipt
- [ ] Handle `Content-Encoding: gzip` for compressed request bodies
- [ ] Set maximum request body size (configurable, default 1 MB)
- [ ] Unit tests for single event ingestion
- [ ] Unit tests for batch event ingestion
- [ ] Unit tests for validation rejection
- [ ] Unit tests for gzip request body handling

### API Key Authentication Per Project

- [ ] Each project has one or more API keys (stored as SHA-256 hashes in Postgres)
- [ ] Authenticate via `X-Prism-Key` header or `Authorization: Bearer <key>` header
- [ ] Look up project by API key hash, inject `project_id` into the event
- [ ] Reject unauthenticated requests with 401
- [ ] Reject requests with invalid API keys with 403
- [ ] Rate limit by API key (not just IP) to prevent abuse
- [ ] Log authentication failures for security monitoring
- [ ] Unit tests for valid API key authentication
- [ ] Unit tests for missing/invalid API key rejection

### Rate Limiting

- [ ] Implement token bucket rate limiter per project (configurable, default 1000 events/minute)
- [ ] Return 429 Too Many Requests with `Retry-After` header when limit exceeded
- [ ] Track rate limit usage in Redis for distributed deployments
- [ ] Exempt internal/admin requests from rate limiting
- [ ] Unit tests for rate limiting enforcement and headers

### Batch Ingestion

- [ ] Accept request body as JSON array of events: `[{event1}, {event2}, ...]`
- [ ] Validate each event independently -- accept valid events, reject invalid ones
- [ ] Return partial success: `{ "accepted": ["id1", "id2"], "rejected": [{ "index": 2, "error": "..." }] }`
- [ ] Limit batch size (configurable, default 100 events per request)
- [ ] Unit tests for partial batch acceptance
- [ ] Unit tests for batch size limit enforcement

### Write Raw Event to Blob Storage

- [ ] After validation, pass the event to `EventWriter` for buffered blob storage write
- [ ] Record the blob path and byte offset in the `events_index` table
- [ ] Ensure event_id uniqueness (deduplicate if same event_id arrives twice)
- [ ] Unit tests for event write and index recording

### Update Issue Counter in Postgres

- [ ] Generate fingerprint for the incoming event using `FingerprintGenerator`
- [ ] Call `IssueRepository::findOrCreate()` to get or create the issue row
- [ ] Call `IssueRepository::incrementCount()` and `updateLastSeen()`
- [ ] If this is a new issue (just created), set status to `unresolved`, record `first_seen`
- [ ] If the issue was previously `resolved` and a new event arrives, set status back to `unresolved` (regression detection)
- [ ] Unit tests for new issue creation
- [ ] Unit tests for existing issue count increment
- [ ] Unit tests for regression detection (resolved -> unresolved)

### Publish Lightweight Signal to Redis Pub/Sub

- [ ] After processing, publish a lightweight signal to Redis channel `prism:events:{project_id}`
- [ ] Signal payload: `{ "event_id": "...", "issue_id": ..., "fingerprint": "...", "level": "...", "title": "...", "is_new_issue": true/false, "is_regression": true/false }`
- [ ] Do NOT include the full event in the signal (it's in blob storage) -- keep the signal small for low latency
- [ ] Signal is consumed by Ripple WebSocket for real-time dashboard updates
- [ ] If Redis is unavailable, log a warning and continue (error reporting should not fail due to real-time subsystem)
- [ ] Unit tests for signal publishing
- [ ] Unit tests for Redis unavailability handling

### Project Management

- [ ] `POST /api/v1/projects` -- create a new project (admin-authenticated)
  - [ ] Body: `{ "name": "My App", "platform": "php" }`
  - [ ] Response: `{ "project_id": "proj_...", "api_key": "prism_..." }` (API key returned once in plaintext, stored as hash)
- [ ] `GET /api/v1/projects` -- list all projects (admin-authenticated)
- [ ] `GET /api/v1/projects/:id` -- get project details
- [ ] `POST /api/v1/projects/:id/rotate-key` -- rotate API key (invalidate old, generate new)
  - [ ] Return new API key in plaintext (one-time display)
  - [ ] Old key becomes invalid immediately
- [ ] `DELETE /api/v1/projects/:id` -- delete project and all associated data
- [ ] Unit tests for project CRUD operations
- [ ] Unit tests for API key rotation

---

## Phase 3 -- SDKs

### sdk-core (Shared Library)

- [ ] Define the ErrorEvent JSON schema as a shared specification document
- [ ] Implement fingerprinting algorithm (same logic as server, for client-side grouping hints)
- [ ] Implement PII sanitizer: configurable field patterns to redact before sending
  - [ ] Default redaction: `Authorization` header, `Cookie` header, `password` fields, `token` fields, `secret` fields, `credit_card` fields
  - [ ] Pattern-based redaction: credit card numbers (Luhn-valid 13-19 digit sequences), SSN patterns
  - [ ] Configurable allowlist: fields that should never be redacted
  - [ ] Configurable blocklist: additional fields to always redact
- [ ] Implement breadcrumb collector: in-memory ring buffer of recent breadcrumbs (configurable size, default 100)
- [ ] Implement event builder: fluent API for constructing ErrorEvent with all optional fields
- [ ] Implement transport abstraction: interface for sending events to the ingestion API
- [ ] Implement batch transport: buffer events, flush on interval or count threshold
- [ ] Document the shared specification for SDK implementers

### sdk-laravel (`lattice/prism-laravel`)

- [ ] Composer package: `lattice/prism-laravel`
- [ ] Laravel exception handler integration: register a reporting callback that captures exceptions
- [ ] Capture full exception stacktrace with source context (N lines before/after the error line)
- [ ] Capture HTTP request context: URL, method, headers (sanitized), query params, body (sanitized), IP, user agent
- [ ] Capture authenticated user context: user ID, email (if available, with PII settings)
- [ ] Capture Laravel-specific context: route name, middleware, controller/action
- [ ] Capture PHP runtime context: PHP version, extensions, memory usage
- [ ] Breadcrumb integration: capture database queries, HTTP client requests, log messages, cache operations as breadcrumbs
- [ ] Async dispatch: use Laravel's queue system to send events in the background (configurable, default async)
- [ ] Fallback: if queue is unavailable, send synchronously via HTTP
- [ ] Configuration: DSN/API key, environment, release (auto-detect from git), sample rate (0.0-1.0), ignored exceptions list
- [ ] `PrismServiceProvider` for auto-discovery
- [ ] `config/prism.php` publishable configuration
- [ ] Tag support: `Prism::setTag('tenant_id', $tenantId)` for adding custom tags to all events
- [ ] Context support: `Prism::setContext('order', ['id' => 42])` for adding custom context
- [ ] Manual capture: `Prism::captureException($e)`, `Prism::captureMessage('Something happened', 'warning')`
- [ ] Ignore specific exception types: configurable list (e.g., `ModelNotFoundException`, `ValidationException`)
- [ ] Sample rate: only send N% of events (configurable, default 100%)
- [ ] Unit tests for exception capture and event construction
- [ ] Unit tests for PII sanitization
- [ ] Unit tests for breadcrumb collection
- [ ] Unit tests for async dispatch
- [ ] Integration test: throw exception in Laravel app -> event arrives at ingestion API

### sdk-next (Next.js Browser + Node.js)

- [ ] npm package: `@lattice/prism-next`
- [ ] Browser error capture: `window.onerror` handler for uncaught errors
- [ ] Browser unhandled rejection capture: `window.addEventListener('unhandledrejection', ...)`
- [ ] Browser source context: include source file URL and line/column numbers (source map support on server side)
- [ ] Browser request context: `window.location`, `navigator.userAgent`, viewport size, `document.referrer`
- [ ] Browser breadcrumbs: `fetch`/`XMLHttpRequest` interception, `console.error` interception, DOM click events, navigation events
- [ ] `sendBeacon` transport: use `navigator.sendBeacon` for reliable delivery on page unload
- [ ] Batched transport: buffer events, flush every N seconds or N events
- [ ] Node.js SSR error capture: `process.on('uncaughtException', ...)`, `process.on('unhandledRejection', ...)`
- [ ] Node.js API route error capture: middleware that wraps Next.js API route handlers
- [ ] Node.js request context: URL, method, headers (sanitized), query params
- [ ] Configuration: DSN, environment, release, sample rate, ignored errors (by message pattern)
- [ ] Next.js integration: `withPrism()` wrapper for `next.config.js`
- [ ] React error boundary component: `<PrismErrorBoundary>` that captures React render errors
- [ ] Source map upload CLI: `prism sourcemaps upload --release=v2.3.1 --path=.next/` for server-side source map resolution
- [ ] Tree-shakeable: browser bundle should be < 5 KB gzipped
- [ ] Unit tests for browser error capture
- [ ] Unit tests for Node.js error capture
- [ ] Unit tests for breadcrumb collection
- [ ] Unit tests for sendBeacon transport

### sdk-nest (NestJS)

- [ ] npm package: `@lattice/prism-nest`
- [ ] NestJS global exception filter: `@Catch()` filter that captures all unhandled exceptions
- [ ] HTTP request context: URL, method, headers (sanitized), query params, body (sanitized)
- [ ] NestJS-specific context: controller name, handler name, route path, guards, interceptors
- [ ] Correlation ID tracking: extract or generate `X-Correlation-ID` header, attach to all events
- [ ] Breadcrumbs: HTTP client requests (Axios interceptor), database queries (TypeORM subscriber), log messages
- [ ] Async transport: non-blocking HTTP client for sending events
- [ ] Configuration: DSN, environment, release, sample rate, ignored exceptions
- [ ] `PrismModule` for NestJS dependency injection registration
- [ ] Manual capture: `PrismService.captureException(error)`, `PrismService.captureMessage(message, level)`
- [ ] Decorators: `@CaptureErrors()` method decorator for wrapping specific handlers
- [ ] Unit tests for exception filter
- [ ] Unit tests for correlation ID handling
- [ ] Unit tests for breadcrumb collection
- [ ] Integration test: throw exception in NestJS handler -> event arrives at ingestion API

---

## Phase 4 -- Web SPA

### Project Scaffolding

- [ ] Initialize frontend with Vite + React + TypeScript
- [ ] Install and configure TailwindCSS with dark/light theme support (dark default)
- [ ] Configure path aliases (`@/components`, `@/api`, `@/hooks`, `@/pages`)
- [ ] Set up ESLint + Prettier
- [ ] Set up Vitest for unit tests
- [ ] Create `vite.config.ts` with proxy to LatticePHP backend (dev mode)
- [ ] Create production build config (output to `dist/` for embedding in LatticePHP)
- [ ] Create API client module with typed functions for all endpoints
- [ ] Create WebSocket connection manager (via Ripple) for real-time updates

### Layout Shell

- [ ] Create sidebar navigation: Projects, Issues, Live Feed, Settings
- [ ] Create header: current project selector (dropdown), environment filter, search
- [ ] Create breadcrumbs component
- [ ] Active page indicator in sidebar
- [ ] Loading skeleton states for all pages
- [ ] Error boundary with retry functionality

### Projects List Page

- [ ] List all projects with name, platform, error count (last 24h), last error timestamp
- [ ] Create project button with modal (name, platform)
- [ ] Project settings: rename, rotate API key, delete (with confirmation)
- [ ] Display API key setup instructions per platform (copy-paste SDK install commands)
- [ ] Empty state for no projects

### Issues List Page

- [ ] Paginated table of issues for the selected project
- [ ] Columns: status icon, level badge, title (exception class + message), event count, last seen, environment
- [ ] Status column: colored dot (red = unresolved, green = resolved, gray = ignored)
- [ ] Level column: badge (red = error/fatal, yellow = warning, blue = info)
- [ ] Sort by: last seen (default), first seen, event count
- [ ] Filter by: status (unresolved/resolved/ignored), level (error/warning/fatal), environment (production/staging/development), platform
- [ ] Search: full-text search across issue title (exception type + message)
- [ ] Bulk actions: select multiple issues, resolve all, ignore all
- [ ] Click row to navigate to issue detail page
- [ ] Real-time: new issues appear at top with highlight animation, existing issue counts update live via WebSocket
- [ ] Regression indicator: badge on issues that were resolved but have new events
- [ ] Empty state for no issues matching filters
- [ ] Pagination controls: previous/next, page size selector

### Issue Detail Page

- [ ] Issue header: exception type, message, status badge, level badge, event count, first/last seen
- [ ] Action buttons: Resolve, Ignore, Unresolve (based on current status)
- [ ] Stacktrace viewer: collapsible list of stack frames
  - [ ] Each frame shows: file path, line number, function/method name
  - [ ] Expandable source context: highlighted code snippet around the error line
  - [ ] Distinguish application frames from vendor/library frames (collapse vendor frames by default)
  - [ ] "In app" vs "vendor" toggle to show/hide library frames
- [ ] Sample events section: list of recent events for this issue (from events_index)
  - [ ] Click an event to view its full context (request, user, tags, breadcrumbs)
- [ ] Tags section: display all unique tags across events for this issue
- [ ] Trend chart: event count per hour/day over the last 7 days (sparkline or bar chart)
- [ ] Environment breakdown: which environments this issue appears in, with counts
- [ ] Release breakdown: which releases this issue appears in, with counts
- [ ] First seen / last seen timestamps with relative time ("3 hours ago")
- [ ] Breadcrumbs viewer: chronological list of breadcrumbs from a sample event
- [ ] Context viewer: request context, user context, runtime context from a sample event
- [ ] Copy issue URL to clipboard

### Live Feed Page

- [ ] Real-time stream of incoming errors via Ripple WebSocket
- [ ] Each entry: timestamp, level badge, exception type, message, environment, project
- [ ] Auto-scroll to newest (with pause button)
- [ ] Filter by level, environment, project
- [ ] Click an entry to navigate to its issue detail page
- [ ] Connection status indicator: connected (green), reconnecting (yellow), disconnected (red)
- [ ] Sound notification option for new errors (toggle on/off)
- [ ] Maximum displayed entries (configurable, default 500, older entries removed from DOM)

### Issue Actions

- [ ] Resolve: set issue status to `resolved`, record who resolved and when
- [ ] Ignore: set issue status to `ignored`, stop counting new events (still stored but not surfaced)
- [ ] Unresolve: set issue status back to `unresolved` (manual reopen)
- [ ] Confirmation dialog for bulk actions
- [ ] Optimistic UI update: change status badge immediately, revert if API call fails
- [ ] Toast notification for action feedback

### Release Tracking

- [ ] Display release version (from event `release` field) on issue detail page
- [ ] Filter issues by release on the issues list page
- [ ] Release overview: count of new issues per release, total events per release
- [ ] Detect regressions: if a resolved issue reappears in a new release, flag it

### Environment Filtering

- [ ] Global environment selector in the header (applies to all pages)
- [ ] Options: All, Production, Staging, Development (auto-detected from event data)
- [ ] Persist selected environment in URL query parameter for shareable links
- [ ] Filter issues list, issue detail events, live feed, and stats by selected environment

### Search

- [ ] Search input in the header with keyboard shortcut (`/` to focus)
- [ ] Search across issue titles (exception type + message)
- [ ] Debounced search (300ms) to avoid excessive API calls
- [ ] Search results update the issues list in real-time
- [ ] Clear search button

---

## Phase 5 -- CLI TUI

### Interactive TUI (`php lattice prism`)

- [ ] Create `php lattice prism` command -- interactive TUI entry point
- [ ] Dashboard view: project selector, top errors (last 24h), live error feed
  - [ ] Top errors: table showing issue title, count, last seen, status
  - [ ] Live feed: streaming new errors as they arrive via Redis subscription
  - [ ] Stats bar: total errors today, unresolved issues, new issues today
- [ ] Issue list view: scrollable table with color-coded levels and statuses
  - [ ] Arrow key navigation, `/` to search, `Enter` to open issue detail
  - [ ] Filter by status: `u` unresolved, `r` resolved, `i` ignored, `a` all
- [ ] Issue detail view: exception type, message, stacktrace, event count, first/last seen
  - [ ] Stacktrace displayed with syntax highlighting (file paths in cyan, line numbers in yellow)
  - [ ] Action keys: `R` resolve, `I` ignore, `U` unresolve
- [ ] Keyboard shortcuts: `q` quit, `r` refresh, `Tab` switch views, `/` search, `?` help
- [ ] Color scheme: error = red, warning = yellow, info = blue, resolved = green, ignored = gray

### Non-Interactive CLI Commands

- [ ] `php lattice prism:issues` -- print issues list as formatted table, exit
  - [ ] Support `--project=<id>` filter
  - [ ] Support `--status=unresolved` filter
  - [ ] Support `--level=error` filter
  - [ ] Support `--limit=25` pagination
  - [ ] Support `--json` flag for machine-readable output
- [ ] `php lattice prism:issue <id>` -- print issue detail with stacktrace, exit
  - [ ] Display exception type, message, full stacktrace
  - [ ] Display event count, first/last seen, status, environments
  - [ ] Support `--json` flag for machine-readable output
- [ ] `php lattice prism:live` -- live error stream (tail mode), non-interactive
  - [ ] Print each new error as it arrives: timestamp, level, exception type, message
  - [ ] Support `--project=<id>` filter
  - [ ] Support `--level=error` filter
  - [ ] Color-coded output (red for errors, yellow for warnings)
  - [ ] Ctrl+C to exit
- [ ] `php lattice prism:projects` -- list projects with error counts
  - [ ] Display: project name, ID, platform, total issues, errors last 24h
  - [ ] Support `--json` flag
- [ ] `php lattice prism:stats` -- error counts and trends
  - [ ] Display: total events (last 24h, 7d, 30d), unresolved issues, new issues today, top 5 errors
  - [ ] Support `--project=<id>` filter
  - [ ] Support `--json` flag
- [ ] Unit tests for all CLI commands
- [ ] Unit tests for `--json` output format

---

## Phase 6 -- Polish

### Alerting

- [ ] Define alert rules in configuration: `prism.alerting.rules`
- [ ] Rule types:
  - [ ] `new_issue`: alert when a completely new issue appears (first occurrence)
  - [ ] `regression`: alert when a resolved issue receives new events
  - [ ] `threshold`: alert when an issue exceeds N events in M minutes
  - [ ] `spike`: alert when error rate exceeds N% increase over baseline
- [ ] Alert channels:
  - [ ] Slack webhook: configurable webhook URL, formatted message with issue title, count, link to dashboard
  - [ ] Email: via `lattice/mail` (if available), HTML-formatted error summary
  - [ ] Generic webhook: POST to configurable URL with JSON payload
- [ ] Alert cooldown: don't re-alert for the same issue within N minutes (configurable, default 60)
- [ ] Alert history: store recent alerts in Postgres for display in dashboard
- [ ] Dashboard alert panel: show recent alerts with timestamps and channels
- [ ] Unit tests for each rule type evaluation
- [ ] Unit tests for alert cooldown logic
- [ ] Integration test for Slack webhook delivery

### Source Map Support for JavaScript Errors

- [ ] Source map upload endpoint: `POST /api/v1/sourcemaps` -- accept source map files keyed by release version
- [ ] Store source maps in blob storage: `sourcemaps/{project_id}/{release}/{file.map}`
- [ ] Source map processor: on JavaScript event ingestion, resolve minified stacktrace frames to original source using uploaded source maps
- [ ] Update `StackFrame` with resolved file, line, column, function name
- [ ] Source map validation: verify the source map is valid and matches the referenced source file
- [ ] Automatic cleanup: delete source maps older than N releases (configurable retention)
- [ ] CLI command: `php lattice prism:sourcemaps:upload --project=<id> --release=v1.0 --path=./dist/`
- [ ] Unit tests for source map resolution
- [ ] Unit tests for minified -> original stacktrace mapping

### Error Grouping Tuning

- [ ] Admin UI for viewing and adjusting fingerprint rules per project
- [ ] Merge issues: combine two issues that represent the same error (different fingerprints due to code changes)
- [ ] Split issues: separate events from an over-grouped issue into new issues
- [ ] Custom fingerprint rules: define rules like "group all errors from `PaymentService` into one issue"
- [ ] Preview fingerprint changes: show how existing events would be regrouped before applying
- [ ] Unit tests for merge and split operations

### Retention Policies

- [ ] Configurable retention per project: keep raw events for N days (default 90)
- [ ] Auto-archive: move blobs older than retention period to cool/archive storage tier
- [ ] Auto-delete: permanently delete blobs older than N days (configurable, default 365)
- [ ] Clean up `events_index` rows for deleted/archived blobs
- [ ] Retain issue summaries in Postgres indefinitely (tiny footprint)
- [ ] CLI command: `php lattice prism:cleanup` -- run retention policy manually
- [ ] Scheduled task: run retention policy daily via cron
- [ ] Unit tests for retention policy enforcement
- [ ] Unit tests for storage tier migration

### Documentation

- [ ] Installation guide (Composer require, module registration, config publish, Postgres migration)
- [ ] Quick start: capture first error in 5 minutes
- [ ] SDK installation guide: Laravel, Next.js, NestJS (step-by-step)
- [ ] Event contract reference (all fields documented)
- [ ] Fingerprinting explanation and customization guide
- [ ] Storage architecture explanation (blob storage strategy, cost analysis)
- [ ] API reference (all endpoints with request/response examples)
- [ ] Alerting configuration guide
- [ ] Source map setup guide
- [ ] CLI TUI usage guide
- [ ] Self-hosting guide: requirements, recommended infrastructure, scaling tips
- [ ] Migration guide from Sentry / BugSnag (event format mapping, SDK swap)

### Tests

- [ ] Unit tests for `FingerprintGenerator` -- all normalization steps, all platforms
- [ ] Unit tests for `EventWriter` -- buffering, flushing, GZIP compression, NDJSON format
- [ ] Unit tests for `EventReader` -- decompression, parsing, filtering
- [ ] Unit tests for `LocalFilesystemAdapter` -- all CRUD operations
- [ ] Unit tests for `S3Adapter` -- all CRUD operations (mock client)
- [ ] Unit tests for `AzureBlobAdapter` -- all CRUD operations (mock client)
- [ ] Unit tests for `IssueRepository` -- upsert, increment, list with filters, status updates
- [ ] Unit tests for `EventIndexRepository` -- insert, query by issue, query by event ID
- [ ] Unit tests for ingestion API -- authentication, validation, single/batch, rate limiting
- [ ] Unit tests for project management API -- CRUD, key rotation
- [ ] Unit tests for alert rule evaluation -- all rule types, cooldown
- [ ] Unit tests for PII sanitizer -- all field patterns, custom rules
- [ ] Integration test: SDK captures exception -> ingestion API processes -> issue created in Postgres -> event stored in blob -> signal published to Redis
- [ ] Integration test: resolve issue -> new event arrives -> issue becomes unresolved (regression)
- [ ] Integration test: source map upload -> JS event ingestion -> stacktrace resolved
- [ ] Integration test: retention policy deletes old blobs and index entries
- [ ] Performance test: ingest 10,000 events/minute without backpressure
- [ ] Performance test: issues list query with 100,000 issues, filtered and paginated
- [ ] E2E test (frontend): issues list page renders, filters work, click through to detail
- [ ] E2E test (frontend): issue detail page shows stacktrace, actions work (resolve/ignore)
- [ ] E2E test (frontend): live feed page streams errors in real-time
- [ ] E2E test (frontend): project creation and API key display
