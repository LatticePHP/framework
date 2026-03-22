# 07 — Chronos: Workflow Execution Dashboard

> Visual dashboard and management UI for LatticePHP's durable workflow engine with a Next.js SPA and CLI TUI

## Dependencies
- `lattice/workflow` (core workflow engine — Chronos API lives here)
- `lattice/workflow-store` (persistence layer — reads execution and event data)
- `lattice/http` (HTTP routing, middleware, streaming responses)
- Optional: `lattice/auth` (for guard integration), `lattice/ripple` (for WebSocket real-time instead of SSE)

## Frontend Stack
- **Next.js** (React framework) or React SPA
- **NextUI** (component library)
- **TailwindCSS** (utility-first styling, dark/light theming)
- **TanStack Query** (server-state caching, automatic refetching, SSE integration)
- **TanStack Router** (if standalone SPA) or Next.js App Router
- **Zustand** (client-side state management)
- **Zod** (runtime validation for API responses and form inputs)

## Subtasks

### 1. [ ] API layer — ChronosModule, workflow list/detail/events endpoints

#### Module and Configuration
- Create `Chronos/Config/chronos.php` configuration file (route prefix, guard settings, SSE heartbeat interval, pagination defaults, data retention window)
- Create `ChronosModule.php` with `#[Module]` attribute: register all Chronos routes, bind middleware, publish config
- Create `ChronosServiceProvider.php` for standalone registration outside the module system
- Implement `ChronosGuard.php`: admin-only access guard with configurable callback/gate
- Add CORS configuration support for standalone SPA mode (development)
- Register Chronos routes only when `chronos.enabled = true`
- Unit tests for ChronosGuard (authorized/unauthorized), module registration

#### Workflow List Endpoint
- `GET /api/chronos/workflows` — return paginated list of workflow executions
- Implement cursor-based pagination (page, per_page, cursor)
- Implement status filter (`?status=running,failed` — comma-separated)
- Implement type filter (`?type=OrderWorkflow`)
- Implement date range filter (`?from=2026-01-01&to=2026-03-22`)
- Implement search by workflow ID (`?search=wf-abc`)
- Implement sorting (`?sort=started_at&order=desc`)
- Return consistent response envelope: `{ "data": [...], "meta": { "page", "per_page", "total", "has_more" } }`
- Include summary fields: id, type, status, started_at, duration_ms, last_event_type, last_event_at
- Add rate limiting to list endpoint
- Unit tests for each filter, pagination, sorting, response envelope

#### Workflow Detail Endpoint
- `GET /api/chronos/workflows/:id` — return full execution detail
- Include: id, type, status, input, output, started_at, completed_at, duration_ms, parent_workflow_id
- Include inline event history (first N events, with `has_more` flag)
- Include registered signal method names and parameter signatures
- Include registered query method names and parameter signatures
- Return 404 with structured error for unknown workflow IDs
- Unit tests for valid ID, unknown ID (404), response structure

#### Event History Endpoint
- `GET /api/chronos/workflows/:id/events` — paginated event history
- Support pagination for executions with thousands of events (`?page=1&per_page=50`)
- Include event fields: id, type, timestamp, data (input/output/error), duration_ms
- Support event type filter (`?event_type=ActivityCompleted,ActivityFailed`)
- Support ordering (chronological ascending default, descending optional)
- Unit tests for pagination, event type filter, ordering

#### Activity Detail Endpoint
- `GET /api/chronos/workflows/:id/activities/:activityId` — detailed activity info
- Include: activity type, input, output, scheduled_at, started_at, completed_at
- Include: queue time, execution time, retry count, retry policy, individual attempt errors
- Include: worker/process identifier and heartbeat history (if tracked)
- Unit tests for activity detail response

- **Verify:** `GET /api/chronos/workflows` returns paginated workflow list with filters; `GET /api/chronos/workflows/:id` returns full detail with event history and signal/query signatures

### 2. [ ] API — signal/retry/cancel/terminate action endpoints

#### Signal Endpoint
- `POST /api/chronos/workflows/:id/signal`
- Accept body: `{ "signal": "methodName", "payload": { ... } }`
- Validate signal name against registered signal methods
- Validate payload against signal method parameter types
- Return error if workflow is not in a signalable state
- Record signal event in execution history
- Unit tests for valid signal, invalid signal name, non-signalable state

#### Retry Endpoint
- `POST /api/chronos/workflows/:id/retry`
- Validate workflow is in failed state
- Re-enqueue from last checkpoint
- Return new execution state
- Unit tests for successful retry, reject for non-failed workflow

#### Cancel Endpoint
- `POST /api/chronos/workflows/:id/cancel`
- Validate workflow is in a cancellable state (running, waiting)
- Dispatch cancellation request to workflow engine (cancellation is async)
- Return acknowledgement
- Unit tests for successful cancel, reject for non-cancellable state

#### Terminate Endpoint
- `POST /api/chronos/workflows/:id/terminate`
- Require explicit confirmation: `{ "confirm": true }`
- Terminate without cleanup
- Record termination event with operator metadata
- Unit tests for successful terminate, missing confirmation, reject for invalid state

#### Query Execution Endpoint
- `POST /api/chronos/workflows/:id/query`
- Accept body: `{ "query": "methodName", "args": [ ... ] }`
- Validate query name against registered query methods
- Execute query and return result as JSON
- Timeout protection for long-running queries
- Unit tests for valid query, unknown query name, timeout

- **Verify:** Signal sends to a running workflow and appears in event history; retry re-enqueues a failed workflow; cancel dispatches cancellation; terminate halts immediately with confirmation

### 3. [ ] API — SSE real-time stream + stats/metrics endpoint

#### SSE Endpoint
- `GET /api/chronos/stream` — Server-Sent Events for real-time updates
- Support global stream (all execution state changes)
- Support per-execution stream (`?workflow_id=:id`)
- Emit `status_changed` events (workflow status transitions)
- Emit `event_added` events (new events appended to execution history)
- Emit `stats_updated` events (periodic stats refresh)
- Implement heartbeat/keep-alive pings (configurable interval, default 15s)
- Support `Last-Event-ID` header for reconnection and missed event replay
- Implement connection cleanup on client disconnect
- Set headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`
- Integration tests for SSE connection, event emission, reconnection

#### Stats/Metrics Endpoint
- `GET /api/chronos/stats` — aggregate workflow metrics
- Return: `running` (currently running count), `completed_today` (last 24h), `failed` (not yet retried), `cancelled` (today)
- Return: `avg_duration_ms` (completed workflows, last 24h)
- Return: `by_type` (running/completed/failed counts per workflow type)
- Return: `hourly_completions` (array of counts per hour, last 24h, for chart)
- Implement caching (5-10 seconds) to avoid expensive queries on rapid refresh
- Unit tests for stats accuracy and caching behavior

- **Verify:** SSE endpoint streams `status_changed` events when a workflow transitions state; stats endpoint returns correct running/completed/failed counts

### 4. [ ] Frontend setup — Next.js + NextUI + Tailwind + TanStack Query + Zustand

#### Project Scaffolding
- Initialize Next.js project with TypeScript in `projects/chronos/frontend/`
- Install and configure NextUI component library
- Install and configure TailwindCSS with dark/light theme support (dark-first)
- Install and configure TanStack Query for server-state management
- Install and configure TanStack Router (if standalone) or use Next.js App Router
- Install and configure Zustand for client-side state (theme, filters, UI state)
- Install and configure Zod for API response validation and form input validation
- Configure path aliases (`@/components`, `@/api`, `@/hooks`, `@/stores`, `@/lib`)
- Set up ESLint + Prettier
- Set up Vitest for component/unit tests
- Configure development proxy to LatticePHP backend API
- Configure production build output for embedding (static assets to `dist/`)

#### API Client Layer
- Create typed HTTP client wrapper with base URL config using TanStack Query
- Define Zod schemas for all API response types (workflow list, detail, events, stats, actions)
- Create typed query hooks: `useWorkflows()`, `useWorkflow(id)`, `useWorkflowEvents(id)`, `useStats()`
- Create typed mutation hooks: `useSignalWorkflow()`, `useRetryWorkflow()`, `useCancelWorkflow()`, `useTerminateWorkflow()`, `useQueryWorkflow()`
- Create SSE connection manager hook (`useSSE`) with auto-reconnect and event parsing
- Validate all API responses with Zod schemas at runtime

#### Layout Shell
- Create `Sidebar` component (NextUI navigation): links to Dashboard and Workflows, Chronos branding
- Create `Header` component: page title, optional actions slot
- Create `Breadcrumbs` component: auto-generated from route
- Create root layout with sidebar + main content area
- Implement sidebar collapse for mobile/responsive
- Add loading indicator (NextUI progress bar) for page transitions
- Implement dark/light theme toggle (sun/moon icon in header) using Zustand for state
- Persist theme preference in localStorage, respect OS `prefers-color-scheme`
- Dark theme as default

- **Verify:** Next.js dev server starts, renders layout shell with sidebar navigation, theme toggle works, API client connects to backend

### 5. [ ] Frontend — workflow list page (table, filters, search, pagination)

#### WorkflowTable Component
- Build with NextUI `Table` component
- Columns: ID (truncated, copyable), Type, Status, Started, Duration, Last Event
- Row click navigates to workflow detail page
- Implement column sorting (click column header to toggle sort)
- Empty state component when no workflows found

#### StatusBadge Component
- Color-coded NextUI `Chip` badges: green=running, blue=completed, red=failed, yellow=cancelled, purple=compensating
- Animate on status change via SSE

#### WorkflowFilters Component
- Status multi-select (NextUI `Select` with multiple)
- Type dropdown (NextUI `Select`)
- Date range picker (NextUI `DateRangePicker` or custom)
- Search input (NextUI `Input`) for workflow ID search
- Sync all filter state to URL query parameters (shareable URLs)
- Restore filter state from URL on page load
- Store filter preferences in Zustand

#### Pagination
- NextUI `Pagination` component with previous/next and page size selector
- Cursor-based pagination integration with TanStack Query

#### SSE Integration
- Integrate SSE for live status updates in the table
- Status badge animates on change (brief highlight)
- New workflows appear at top of list when SSE delivers them

- **Verify:** Workflow list page renders table with data from API; status filter narrows results; search by ID works; pagination navigates correctly; SSE updates status badges in real-time

### 6. [ ] Frontend — workflow detail page (header, status badge, event timeline)

#### Detail Page Layout
- Header section: workflow ID (copyable), type, status badge, started/completed timestamps, duration
- Action buttons row: Signal, Retry (if failed), Cancel (if running), Terminate (if running)
- Tab navigation (NextUI `Tabs`): Timeline, Activities, Queries, Compensation
- Display workflow input and output as formatted, collapsible JSON (NextUI `Accordion`)
- Display parent/child workflow links

#### EventTimeline Component
- Vertical timeline with events in chronological order
- `EventCard` component: event type icon, timestamp, summary, expandable detail
- Color-code cards by type: green=success, red=failure, blue=info, orange=warning
- Show duration between consecutive events (time gaps)
- Expandable JSON viewer for event data (input, output, error)
- Highlight error events with full stack trace display
- "Pin to bottom" toggle for auto-scrolling during live execution
- Pagination/infinite scroll for executions with many events
- Event type filter chips above timeline

#### ActivityPanel Component
- Slide-out panel or expandable section for activity details
- Display: activity type, status, timing breakdown (queued, executing, total)
- Display: input parameters and output/return value as formatted JSON
- Display: retry attempts with individual errors and timestamps
- Display: retry policy (max attempts, backoff strategy, timeout)
- Visual timing bar (queued vs execution time proportional)

#### SSE Integration
- Subscribe to per-execution SSE stream for live updates
- New events append to timeline in real-time
- Status badge and header update on state transitions

- **Verify:** Detail page shows workflow metadata, event timeline renders events chronologically, expanding an event shows full data, SSE appends new events live

### 7. [ ] Frontend — signal modal, query panel, compensation visualization

#### SignalModal Component
- NextUI `Modal` dialog
- Fetch available signal methods from workflow detail endpoint
- Signal method selector (NextUI `Select` dropdown)
- Dynamically generate form fields based on parameter signatures:
  - String: NextUI `Input`
  - Integer: NextUI `Input` type=number
  - Boolean: NextUI `Switch`
  - Array/object: JSON editor textarea with Zod validation
- Confirmation step before sending (signal name + payload preview)
- Display success/error result inline in modal
- Close and refresh timeline on success

#### QueryPanel Component
- Panel within the Queries tab
- Query method selector (NextUI `Select` dropdown)
- Dynamic argument fields based on query method signatures
- Display query result as formatted JSON
- Error state for failed queries
- Session history of executed queries (Zustand, cleared on page leave)
- Copy result to clipboard button

#### CompensationGraph Component
- Visual representation of saga compensation flow
- Forward execution path as sequence of activity nodes
- Mark activities that triggered compensation (red/orange highlight)
- Reverse compensation path with compensation activity nodes
- Status per node: completed (green), compensated (orange), failed-to-compensate (red)
- Click node to open ActivityPanel
- Legend explaining node colors and connection types

#### Stats Dashboard
- `StatsBar` component: row of NextUI `Card` stat cards at top of Dashboard/List page
- Display: Running, Completed today, Failed, Avg duration
- Animate counter changes on SSE `stats_updated` events
- Hourly completions chart (bar/line using a lightweight chart library)
- Workflow type breakdown chart

- **Verify:** Signal modal dynamically generates fields from method signatures, sends signal, and result appears in timeline; query panel executes and displays results; compensation graph visualizes saga flow

### 8. [ ] CLI TUI — `php lattice chronos` interactive + non-interactive commands

#### Interactive TUI
- `php lattice chronos` — main interactive TUI entry point
- Dashboard view: live-updating workflow stats (running, completed, failed counts)
- Workflow list view: scrollable table with status colors (green=completed, yellow=running, red=failed)
- Arrow key navigation, search/filter with `/`
- Workflow detail view: workflow metadata, current status, duration
- Event timeline view: vertical scrollable timeline with timestamps
- Activity detail panel: input/output, duration, retry count

#### Live and Interactive Features
- Live tail mode: `php lattice chronos --tail` streams new workflow events in real-time
- Signal sending: interactive prompt to select signal method and enter payload (JSON)
- Query execution: interactive prompt to select query and display result
- Workflow actions: retry/cancel/terminate with confirmation prompt

#### Visual Design
- Color-coded event types: ActivityScheduled=blue, ActivityCompleted=green, ActivityFailed=red, SignalReceived=yellow, TimerStarted=cyan
- Keyboard shortcuts: `q` quit, `r` refresh, `f` filter, `s` signal, `/` search, `?` help
- Compact mode for narrow terminals

#### Non-Interactive CLI Commands
- `php lattice chronos:list` — print workflow list as table
- `php lattice chronos:show <id>` — print workflow detail + event history
- `php lattice chronos:events <id>` — print event timeline
- `php lattice chronos:signal <id> <method> <payload>` — send signal
- `php lattice chronos:retry <id>` — retry workflow
- All commands support `--json` flag for machine-readable output
- Unit tests for all CLI commands

- **Verify:** `php lattice chronos` TUI shows live workflow stats; `chronos:list` outputs formatted table; `chronos:signal` sends a signal to a running workflow

### 9. [ ] Tests — API endpoint tests + frontend component tests

#### API Endpoint Tests
- Test `GET /api/chronos/workflows` — paginated list, all filters (status, type, date range, search), sorting, pagination boundaries
- Test `GET /api/chronos/workflows/:id` — valid ID returns detail, unknown ID returns 404
- Test `GET /api/chronos/workflows/:id/events` — paginated events, event type filter
- Test `POST /api/chronos/workflows/:id/signal` — valid signal, invalid signal name, non-signalable state
- Test `POST /api/chronos/workflows/:id/retry` — retry failed, reject non-failed
- Test `POST /api/chronos/workflows/:id/cancel` — cancel running, reject non-running
- Test `POST /api/chronos/workflows/:id/terminate` — terminate with confirmation, reject without confirmation
- Test `POST /api/chronos/workflows/:id/query` — valid query, unknown query name
- Test `GET /api/chronos/workflows/:id/activities/:activityId` — activity detail
- Test `GET /api/chronos/stats` — accurate counts
- Test `GET /api/chronos/stream` — SSE connection, event reception, Last-Event-ID reconnection
- Test ChronosGuard — blocks unauthenticated, blocks non-admin, allows admin
- Test rate limiting on list endpoint

#### Frontend Component Tests (Vitest)
- Test WorkflowTable renders with mock data
- Test StatusBadge renders correct colors for each status
- Test WorkflowFilters updates URL and triggers refetch
- Test EventTimeline renders events in order
- Test SignalModal generates dynamic form fields
- Test QueryPanel executes query and displays result
- Test CompensationGraph renders nodes with correct colors
- Test theme toggle switches between dark and light
- Test pagination navigates between pages
- Test SSE hook reconnects on disconnect

- **Verify:** All API endpoint tests pass with realistic mock data; frontend component tests render correctly in Vitest; SSE integration test confirms real-time event delivery

## Integration Verification
- [ ] API returns paginated workflow list with correct filters applied
- [ ] SPA renders workflow list page with data from the API
- [ ] Clicking a workflow navigates to detail page with event timeline
- [ ] Event timeline shows events in chronological order with correct color coding
- [ ] Signal modal sends a signal and the new event appears in the timeline via SSE
- [ ] Retry button re-enqueues a failed workflow and status updates to running
- [ ] Stats bar shows accurate running/completed/failed counts
- [ ] Dark/light theme toggle works and persists across page loads
- [ ] URL-based filtering produces shareable URLs that restore filter state
- [ ] CLI `chronos:list` and `chronos:show` output match API data
- [ ] End-to-end: start a workflow, watch it progress in the dashboard, signal it, see the signal event appear
