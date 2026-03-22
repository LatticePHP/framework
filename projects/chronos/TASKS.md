# Chronos — Task List

---

## Phase 1 — API Layer (in `lattice/workflow`)

### Module & Configuration

- [ ] Create `Chronos/Config/chronos.php` configuration file (route prefix, guard settings, SSE heartbeat interval, pagination defaults, data retention window)
- [ ] Create `ChronosModule.php` — registers all Chronos routes, binds middleware, publishes config
- [ ] Create `ChronosServiceProvider.php` — standalone service provider for registering Chronos outside the module system
- [ ] Implement `ChronosGuard.php` — admin-only access guard with configurable callback/gate
- [ ] Add CORS configuration support for standalone SPA mode (development)
- [ ] Register Chronos routes only when explicitly enabled in config (`chronos.enabled = true`)

### Workflow List Endpoint

- [ ] `GET /api/chronos/workflows` — return paginated list of workflow executions
- [ ] Implement cursor-based pagination (page, per_page, cursor)
- [ ] Implement status filter (`?status=running,failed` — comma-separated, multiple allowed)
- [ ] Implement type filter (`?type=OrderWorkflow`)
- [ ] Implement date range filter (`?from=2026-01-01&to=2026-03-22`)
- [ ] Implement search by workflow ID (`?search=wf-abc`)
- [ ] Implement sorting (`?sort=started_at&order=desc`)
- [ ] Return consistent response envelope with `data` and `meta` (total, has_more, page)
- [ ] Include summary fields per execution: id, type, status, started_at, duration_ms, last_event_type, last_event_at
- [ ] Add rate limiting to list endpoint

### Workflow Detail Endpoint

- [ ] `GET /api/chronos/workflows/:id` — return full execution detail
- [ ] Include execution metadata: id, type, status, input, output, started_at, completed_at, duration_ms, parent_workflow_id (if child)
- [ ] Include inline event history (first N events, with `has_more` flag)
- [ ] Include registered signal method names and their parameter signatures
- [ ] Include registered query method names and their parameter signatures
- [ ] Return 404 with structured error for unknown workflow IDs

### Event History Endpoint

- [ ] `GET /api/chronos/workflows/:id/events` — paginated event history
- [ ] Support pagination for executions with thousands of events (`?page=1&per_page=50`)
- [ ] Include event fields: id, type, timestamp, data (input/output/error), duration_ms (for activity events)
- [ ] Support event type filter (`?event_type=ActivityCompleted,ActivityFailed`)
- [ ] Support ordering (chronological ascending by default, descending optional)

### Workflow Action Endpoints

- [ ] `POST /api/chronos/workflows/:id/signal` — send a signal to a running workflow
  - [ ] Accept body: `{ "signal": "methodName", "payload": { ... } }`
  - [ ] Validate signal name against registered signal methods
  - [ ] Validate payload against signal method parameter types
  - [ ] Return error if workflow is not in a signalable state
  - [ ] Record signal event in execution history
- [ ] `POST /api/chronos/workflows/:id/retry` — retry a failed workflow
  - [ ] Validate workflow is in failed state
  - [ ] Re-enqueue from last checkpoint
  - [ ] Return new execution state
- [ ] `POST /api/chronos/workflows/:id/cancel` — request graceful cancellation
  - [ ] Validate workflow is in a cancellable state (running, waiting)
  - [ ] Dispatch cancellation request to the workflow engine
  - [ ] Return acknowledgement (cancellation is async)
- [ ] `POST /api/chronos/workflows/:id/terminate` — immediately halt execution
  - [ ] Require explicit confirmation field in body (`{ "confirm": true }`)
  - [ ] Terminate without cleanup
  - [ ] Record termination event with operator metadata

### Query Execution Endpoint

- [ ] `POST /api/chronos/workflows/:id/query` — execute a query against workflow state
  - [ ] Accept body: `{ "query": "methodName", "args": [ ... ] }`
  - [ ] Validate query name against registered query methods
  - [ ] Execute query and return result as JSON
  - [ ] Return error if workflow has no state (not yet started, already purged)
  - [ ] Timeout protection for long-running queries

### Activity Detail Endpoint

- [ ] `GET /api/chronos/workflows/:id/activities/:activityId` — detailed activity info
- [ ] Include: activity type, input, output, scheduled_at, started_at, completed_at
- [ ] Include: queue time (started_at - scheduled_at), execution time (completed_at - started_at)
- [ ] Include: retry count, retry policy, individual attempt errors
- [ ] Include: worker/process identifier (if tracked)
- [ ] Include: heartbeat history (if activity supports heartbeats)

### SSE Endpoint

- [ ] `GET /api/chronos/stream` — Server-Sent Events endpoint for real-time updates
- [ ] Support global stream (all execution state changes)
- [ ] Support per-execution stream (`?workflow_id=:id`)
- [ ] Emit `status_changed` events (workflow status transitions)
- [ ] Emit `event_added` events (new events appended to execution history)
- [ ] Emit `stats_updated` events (periodic stats refresh)
- [ ] Implement heartbeat/keep-alive pings (configurable interval, default 15s)
- [ ] Support `Last-Event-ID` header for reconnection and missed event replay
- [ ] Implement connection cleanup on client disconnect
- [ ] Set appropriate headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`

### Stats / Metrics Endpoint

- [ ] `GET /api/chronos/stats` — aggregate workflow metrics
- [ ] Return: `running` — count of currently running executions
- [ ] Return: `completed_today` — count of executions completed in the last 24 hours
- [ ] Return: `failed` — count of currently failed executions (not yet retried)
- [ ] Return: `cancelled` — count of cancelled executions today
- [ ] Return: `avg_duration_ms` — average execution duration for completed workflows (last 24h)
- [ ] Return: `by_type` — breakdown of running/completed/failed counts per workflow type
- [ ] Return: `hourly_completions` — array of completion counts per hour (last 24h, for chart)
- [ ] Implement caching (stats are expensive; cache for 5-10 seconds)

### Testing

- [ ] Unit tests for each controller (mock workflow store)
- [ ] Unit tests for ChronosGuard (authorized / unauthorized)
- [ ] Integration tests for list endpoint with filters, pagination, sorting
- [ ] Integration tests for signal/retry/cancel/terminate actions
- [ ] Integration tests for SSE endpoint (connection, event emission, reconnection)
- [ ] Integration tests for stats endpoint accuracy

---

## Phase 2 — Frontend SPA

### Project Scaffolding

- [ ] Initialize project with Vite (React + TypeScript or Svelte + TypeScript)
- [ ] Install and configure TailwindCSS with dark/light theme support
- [ ] Configure path aliases (`@/components`, `@/api`, `@/hooks`)
- [ ] Set up ESLint + Prettier
- [ ] Set up Vitest for unit tests
- [ ] Create `vite.config.ts` with proxy to LatticePHP backend (dev mode)
- [ ] Create production build config (output to `dist/` for embedding)

### API Client

- [ ] Create HTTP client wrapper (fetch or axios) with base URL config
- [ ] Implement request/response interceptors for error handling
- [ ] Implement typed API functions for each endpoint (workflow list, detail, actions, stats)
- [ ] Create SSE connection manager with auto-reconnect and event parsing
- [ ] Add TypeScript interfaces for all API response types

### Layout Shell

- [ ] Create `Sidebar` component — navigation links (Dashboard, Workflows), Chronos branding
- [ ] Create `Header` component — page title, optional actions slot
- [ ] Create `Breadcrumbs` component — auto-generated from route
- [ ] Create root layout with sidebar + main content area
- [ ] Implement sidebar collapse for mobile/responsive
- [ ] Add loading indicator (top progress bar) for page transitions

### Workflow List Page

- [ ] Create `WorkflowTable` component — table with columns: ID, Type, Status, Started, Duration, Last Event
- [ ] Create `StatusBadge` component — color-coded status pills (green=running, blue=completed, red=failed, yellow=cancelled, purple=compensating)
- [ ] Create `WorkflowFilters` component — status multi-select, type dropdown, date range picker, search input
- [ ] Implement pagination controls (previous/next, page size selector)
- [ ] Implement column sorting (click column header)
- [ ] Implement empty state (no workflows found, no workflows matching filters)
- [ ] Integrate SSE for live status updates in the table (status badge animates on change)
- [ ] Implement row click to navigate to workflow detail
- [ ] Add bulk selection and bulk actions (cancel, terminate) — future enhancement placeholder

### Workflow Detail Page

- [ ] Create detail page layout — header section + tabbed content area
- [ ] Create workflow header: ID, type, status badge, started/completed timestamps, duration
- [ ] Create action buttons: Signal, Retry (if failed), Cancel (if running), Terminate (if running)
- [ ] Create tab navigation: Timeline, Activities, Queries, Compensation
- [ ] Display workflow input and output as formatted, collapsible JSON
- [ ] Display parent workflow link (if this is a child workflow)
- [ ] Display child workflow links (if this workflow spawned children)
- [ ] Integrate SSE for live updates on this specific execution

### Event Timeline Component

- [ ] Create `EventTimeline` component — vertical timeline with events in chronological order
- [ ] Create `EventCard` component — shows event type icon, timestamp, summary, expandable detail
- [ ] Color-code event cards by type (green=success, red=failure, blue=info, orange=warning)
- [ ] Show duration between consecutive events (time gaps)
- [ ] Expandable JSON viewer for event data (input, output, error)
- [ ] Highlight error events with full stack trace display
- [ ] Implement "pin to bottom" toggle for auto-scrolling during live execution
- [ ] Implement pagination/infinite scroll for executions with many events
- [ ] Add event type filter chips above timeline

### Activity Detail Panel

- [ ] Create `ActivityPanel` component — slide-out panel or expandable section
- [ ] Display activity type, status, and timing breakdown (queued, executing, total)
- [ ] Display input parameters as formatted JSON
- [ ] Display output/return value as formatted JSON
- [ ] Display retry attempts: count, individual attempt errors, timestamps
- [ ] Display retry policy: max attempts, backoff strategy, timeout
- [ ] Visual timing bar (queued time vs execution time proportional bar)

### Signal Sending Modal

- [ ] Create `SignalModal` component — modal dialog
- [ ] Fetch available signal methods from workflow detail endpoint
- [ ] Create signal method selector dropdown
- [ ] Dynamically generate form fields based on signal method parameter signatures
  - [ ] String fields: text input
  - [ ] Integer fields: number input
  - [ ] Boolean fields: toggle switch
  - [ ] Array/object fields: JSON editor textarea with validation
- [ ] Show confirmation step before sending (signal name, payload preview)
- [ ] Display success/error result inline in modal
- [ ] Close modal and refresh timeline on success

### Query Execution Panel

- [ ] Create `QueryPanel` component — panel within the detail page Queries tab
- [ ] Fetch available query methods from workflow detail endpoint
- [ ] Create query method selector dropdown
- [ ] Dynamically generate argument fields based on query method signatures
- [ ] Display query result as formatted JSON
- [ ] Display error state for failed queries
- [ ] Keep session history of executed queries (in-memory, cleared on page leave)
- [ ] Copy result to clipboard button

### Compensation / Saga Visualization

- [ ] Create `CompensationGraph` component — visual representation of saga flow
- [ ] Show forward execution path as a sequence of activity nodes
- [ ] Mark activities that triggered compensation (red/orange highlight)
- [ ] Show reverse compensation path with compensation activity nodes
- [ ] Status per node: completed (green), compensated (orange), failed-to-compensate (red)
- [ ] Click a node to open its Activity Detail Panel
- [ ] Optional: DAG layout for complex sagas with parallel branches
- [ ] Legend explaining node colors and connection types

### Stats Dashboard

- [ ] Create `StatsBar` component — row of stat cards at top of Dashboard/List page
- [ ] Display: Running count, Completed today, Failed count, Avg duration
- [ ] Animate counter changes when SSE pushes `stats_updated` events
- [ ] Create `DurationChart` component — bar/line chart of hourly completions (last 24h)
- [ ] Create workflow type breakdown chart (pie or horizontal bar)
- [ ] Auto-refresh stats on configurable interval (default: 10 seconds)

### Dark / Light Theme

- [ ] Implement theme toggle (sun/moon icon in header)
- [ ] Use TailwindCSS `dark:` variant classes throughout all components
- [ ] Persist theme preference in localStorage
- [ ] Respect OS-level `prefers-color-scheme` as default
- [ ] Dark theme as default (developer tool convention)
- [ ] Ensure all charts and graphs adapt to theme

### Responsive Design

- [ ] Sidebar collapses to hamburger menu on screens < 768px
- [ ] Workflow table switches to card layout on mobile
- [ ] Timeline remains usable on mobile (single-column, full-width cards)
- [ ] Modals become full-screen on mobile
- [ ] Filter bar collapses into an expandable filter panel on mobile
- [ ] Stats bar stacks vertically on small screens

---

## Phase 3 — Polish & Production Readiness

### Error Handling & Loading States

- [ ] Create global error boundary component
- [ ] Create reusable loading skeleton components (table skeleton, timeline skeleton, stat card skeleton)
- [ ] Implement retry logic for failed API requests (with exponential backoff)
- [ ] Display user-friendly error messages for common failures (network error, 401, 404, 500)
- [ ] Handle SSE connection drops gracefully (reconnect indicator, stale data warning)
- [ ] Add toast/notification system for action feedback (signal sent, workflow cancelled, etc.)
- [ ] Implement optimistic UI updates for actions (show pending state immediately)

### Keyboard Shortcuts

- [ ] `?` — show keyboard shortcuts help modal
- [ ] `/` — focus search input
- [ ] `j` / `k` — navigate workflow list (up/down)
- [ ] `Enter` — open selected workflow detail
- [ ] `Escape` — close modal / go back
- [ ] `r` — refresh current view
- [ ] `t` — toggle theme
- [ ] `f` — focus filter bar
- [ ] Display shortcut hints in tooltips on relevant UI elements

### URL-Based Filtering (Shareable URLs)

- [ ] Sync all filter state to URL query parameters
- [ ] Restore filter state from URL on page load
- [ ] Update URL without full page reload (pushState)
- [ ] Support deep links to specific workflow detail pages (`/chronos/workflows/:id`)
- [ ] Support deep links with tab selection (`/chronos/workflows/:id?tab=timeline`)
- [ ] Shareable filter URLs (copy URL with current filters applied)

### Export

- [ ] Export single workflow event history as JSON file
- [ ] Export single workflow event history as CSV file
- [ ] Export workflow list (current filter/page) as CSV
- [ ] Copy workflow ID to clipboard (one-click)
- [ ] Copy event data to clipboard (one-click on any JSON block)

### Webhook / Notification on Failure

- [ ] Design webhook configuration in `chronos.php` config
- [ ] Implement webhook dispatch on workflow failure (configurable per workflow type)
- [ ] Support Slack webhook format
- [ ] Support generic HTTP POST webhook
- [ ] Support email notification via `lattice/mail` (if available)
- [ ] Configurable failure threshold (notify only after N failures in M minutes)
- [ ] Webhook retry with exponential backoff

### Documentation

- [ ] API endpoint reference (OpenAPI/Swagger spec)
- [ ] Installation guide (Composer require, module registration, config publish)
- [ ] Configuration reference (all config options explained)
- [ ] Frontend development guide (how to run the SPA in standalone mode)
- [ ] Embedding guide (how to serve compiled assets via LatticePHP route)
- [ ] Guard customization guide (how to replace the default admin guard)
- [ ] SSE integration guide (for custom frontends or third-party tools)
- [ ] Screenshots / GIFs for the README

### Tests — API Endpoints

- [ ] Test `GET /api/chronos/workflows` — returns paginated list
- [ ] Test `GET /api/chronos/workflows` — status filter returns only matching statuses
- [ ] Test `GET /api/chronos/workflows` — type filter returns only matching types
- [ ] Test `GET /api/chronos/workflows` — date range filter works correctly
- [ ] Test `GET /api/chronos/workflows` — search by workflow ID
- [ ] Test `GET /api/chronos/workflows` — sorting (asc/desc by each field)
- [ ] Test `GET /api/chronos/workflows` — pagination (page boundaries, empty pages)
- [ ] Test `GET /api/chronos/workflows/:id` — returns full detail for valid ID
- [ ] Test `GET /api/chronos/workflows/:id` — returns 404 for unknown ID
- [ ] Test `GET /api/chronos/workflows/:id/events` — returns paginated events
- [ ] Test `GET /api/chronos/workflows/:id/events` — event type filter
- [ ] Test `POST /api/chronos/workflows/:id/signal` — sends signal to running workflow
- [ ] Test `POST /api/chronos/workflows/:id/signal` — rejects signal for non-running workflow
- [ ] Test `POST /api/chronos/workflows/:id/signal` — rejects unknown signal name
- [ ] Test `POST /api/chronos/workflows/:id/retry` — retries failed workflow
- [ ] Test `POST /api/chronos/workflows/:id/retry` — rejects retry for non-failed workflow
- [ ] Test `POST /api/chronos/workflows/:id/cancel` — cancels running workflow
- [ ] Test `POST /api/chronos/workflows/:id/cancel` — rejects cancel for non-running workflow
- [ ] Test `POST /api/chronos/workflows/:id/terminate` — terminates running workflow
- [ ] Test `POST /api/chronos/workflows/:id/terminate` — requires confirmation field
- [ ] Test `POST /api/chronos/workflows/:id/query` — executes query and returns result
- [ ] Test `POST /api/chronos/workflows/:id/query` — rejects unknown query name
- [ ] Test `GET /api/chronos/workflows/:id/activities/:activityId` — returns activity detail
- [ ] Test `GET /api/chronos/stats` — returns accurate counts
- [ ] Test `GET /api/chronos/stream` — SSE connection established and events received
- [ ] Test `GET /api/chronos/stream` — reconnection with Last-Event-ID replays missed events
- [ ] Test ChronosGuard — blocks unauthenticated requests
- [ ] Test ChronosGuard — blocks non-admin users
- [ ] Test ChronosGuard — allows admin users
- [ ] Test rate limiting on list endpoint

### Tests — Frontend (E2E)

- [ ] E2E: Dashboard loads and displays stats
- [ ] E2E: Workflow list page renders table with data
- [ ] E2E: Applying status filter updates the table
- [ ] E2E: Applying type filter updates the table
- [ ] E2E: Search by workflow ID filters results
- [ ] E2E: Clicking a workflow row navigates to detail page
- [ ] E2E: Workflow detail page shows correct header information
- [ ] E2E: Event timeline renders events in chronological order
- [ ] E2E: Expanding an event shows full detail
- [ ] E2E: Signal modal opens, accepts input, and sends signal
- [ ] E2E: Query panel executes query and displays result
- [ ] E2E: Retry button retries a failed workflow
- [ ] E2E: Cancel button cancels a running workflow (with confirmation)
- [ ] E2E: Terminate button terminates a workflow (with confirmation)
- [ ] E2E: Theme toggle switches between dark and light
- [ ] E2E: Pagination navigates between pages
- [ ] E2E: URL reflects current filter state
- [ ] E2E: Direct URL to workflow detail page loads correctly
- [ ] E2E: Responsive layout adapts at mobile breakpoint
- [ ] E2E: SSE updates are reflected in real-time (mock SSE server)
- [ ] E2E: Compensation visualization renders saga flow correctly
- [ ] E2E: Export event history downloads JSON file
- [ ] E2E: Keyboard shortcuts work (search focus, navigation, theme toggle)

---

## Phase 4 — CLI Terminal UI (TUI)

### Framework & Entry Point

- [ ] Choose TUI framework: PHP-based (laravel-zero/termwind, php-tui/php-tui) or bridge to a Go/Rust TUI binary
- [ ] `php lattice chronos` — main interactive TUI entry point

### Interactive Dashboard & Views

- [ ] Dashboard view: live-updating workflow stats (running, completed, failed counts)
- [ ] Workflow list view: scrollable table with status colors (green=completed, yellow=running, red=failed)
- [ ] Arrow key navigation, search/filter with `/`
- [ ] Workflow detail view: show workflow metadata, current status, duration
- [ ] Event timeline view: vertical scrollable timeline of all events with timestamps
- [ ] Activity detail panel: show input/output, duration, retry count

### Live & Interactive Features

- [ ] Live tail mode: `php lattice chronos --tail` streams new workflow events in real-time (like `tail -f`)
- [ ] Signal sending: interactive prompt to select signal method and enter payload (JSON)
- [ ] Query execution: interactive prompt to select query method and display result
- [ ] Workflow actions: retry/cancel/terminate with confirmation prompt

### Visual Design

- [ ] Color-coded event types (ActivityScheduled=blue, ActivityCompleted=green, ActivityFailed=red, SignalReceived=yellow, TimerStarted=cyan)
- [ ] Keyboard shortcuts: `q` quit, `r` refresh, `f` filter, `s` signal, `/` search, `?` help
- [ ] Compact mode for narrow terminals

### Non-Interactive CLI Commands

- [ ] `php lattice chronos:list` — non-interactive: print workflow list as table (like `docker ps`)
- [ ] `php lattice chronos:show <id>` — non-interactive: print workflow detail + event history
- [ ] `php lattice chronos:events <id>` — non-interactive: print event timeline
- [ ] `php lattice chronos:signal <id> <method> <payload>` — non-interactive: send signal
- [ ] `php lattice chronos:retry <id>` — non-interactive: retry workflow

### Testing & Documentation

- [ ] Tests for all CLI commands
- [ ] Documentation
