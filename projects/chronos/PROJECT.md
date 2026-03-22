# Chronos — Workflow Execution Dashboard for LatticePHP

## Overview

Chronos is the visual dashboard and management UI for LatticePHP's native durable workflow engine. It serves the same role that **Temporal Web UI** serves for Temporal, or **Laravel Horizon** serves for Laravel queues — but purpose-built for LatticePHP's `lattice/workflow` package.

Chronos is the **#1 differentiator** for the LatticePHP ecosystem. While other PHP frameworks offer workflow engines, none ship a production-grade visual dashboard that lets developers inspect, debug, replay, signal, and manage long-running workflow executions in real time. Chronos makes LatticePHP's durable workflow engine tangible and observable.

### What Chronos Does

- **Lists all workflow executions** with filtering by status, type, and date range
- **Displays the full event history** of any execution as a chronological timeline
- **Sends signals** to running workflows through a dynamic form UI
- **Executes queries** against workflow state and displays results
- **Provides retry/cancel/terminate controls** for workflow lifecycle management
- **Streams real-time updates** so the dashboard reflects execution progress live
- **Visualizes compensation/saga flows** showing which activities were compensated and why
- **Displays aggregate metrics** — running count, completions, failures, average duration

---

## Tech Stack

### Architecture: Standalone SPA + REST API

Chronos is split into two layers, with two user-facing interfaces:

1. **API Layer** — A set of REST endpoints added to the `lattice/workflow` package, registered via a `ChronosModule` and guarded behind admin-only middleware.
2. **Frontend SPA (Web)** — A standalone single-page application that communicates exclusively through those REST endpoints. It can be served by a LatticePHP route (embedded mode) or run standalone (development / separate deployment).
3. **Rich CLI TUI (Terminal)** — An interactive terminal-based dashboard (`php lattice chronos`) that provides the same workflow inspection, signaling, querying, and management capabilities directly in the terminal. Inspired by tools like Claude Code and Python Rich, the TUI features live-updating stats, scrollable workflow lists with color-coded statuses, event timelines, keyboard-driven navigation, and a live tail mode for streaming events in real-time. Non-interactive commands (`chronos:list`, `chronos:show`, `chronos:events`, `chronos:signal`, `chronos:retry`) are also available for scripting and CI/CD pipelines.

### Frontend

| Choice       | Technology                         | Rationale                                                              |
|--------------|------------------------------------|------------------------------------------------------------------------|
| Framework    | **React** (with hooks) or **Svelte** | React for ecosystem breadth; Svelte for smaller bundle and simpler reactivity. Decision deferred to implementation phase — the API contract is framework-agnostic. |
| Build Tool   | **Vite**                           | Fast HMR, native ESM, first-class support for both React and Svelte    |
| Styling      | **TailwindCSS**                    | Utility-first, easy dark/light theming, consistent with Laravel Horizon's design language |
| State        | **TanStack Query** (React) or built-in stores (Svelte) | Server-state caching, automatic refetching, SSE integration           |
| Charts       | **Chart.js** or **Recharts**       | Lightweight, sufficient for counters and duration histograms           |
| Real-time    | **EventSource (SSE)**              | Native browser API, no WebSocket server needed, works through standard HTTP |

### Backend (API Layer)

| Concern       | Technology                                      |
|---------------|-------------------------------------------------|
| Routing       | `lattice/http` route registration               |
| Data access   | `lattice/workflow-store` (reads execution/event data) |
| Auth/guards   | `ChronosModule` middleware (admin-only by default, configurable) |
| SSE           | Native PHP streaming response via `lattice/http` |
| Serialization | JSON responses, consistent envelope format       |

### Serving Modes

| Mode         | Description                                                                                   |
|--------------|-----------------------------------------------------------------------------------------------|
| **Embedded** | The SPA is compiled to static assets and served by a catch-all LatticePHP route at `/chronos`. This is the default for production. |
| **Standalone** | The SPA runs on its own dev server (Vite) and hits the API endpoints on the LatticePHP app. Useful during frontend development. |

---

## Core Features

### 1. Workflow Execution List

A paginated, filterable table of all workflow executions.

- **Columns**: Workflow ID, Type, Status, Started At, Duration, Last Event
- **Filters**: Status (running / completed / failed / cancelled / terminated / compensating), Type (dropdown of registered workflow classes), Date range
- **Search**: Full-text search on workflow ID
- **Sorting**: By start time, duration, status
- **Pagination**: Cursor-based for stable pagination over changing data

### 2. Event History Timeline

A vertical, chronological timeline showing every event in a workflow execution.

- **Event types**: WorkflowStarted, ActivityScheduled, ActivityStarted, ActivityCompleted, ActivityFailed, TimerStarted, TimerFired, SignalReceived, QueryHandled, WorkflowCompleted, WorkflowFailed, WorkflowCancelled, CompensationStarted, CompensationCompleted
- **Each event shows**: Timestamp, event type badge, input/output data (expandable JSON), duration (for activities), error details (for failures)
- **Navigation**: Click an activity event to open the Activity Detail Panel
- **Auto-scroll**: Option to pin to latest event during live execution

### 3. Signal Sending UI

A modal that lets operators send signals to a running workflow.

- **Dynamic form**: Reads the workflow's registered signal methods and their parameter signatures
- **Input fields**: Generates form fields based on parameter types (string, int, bool, array as JSON editor)
- **Confirmation**: Shows a summary before sending
- **Response**: Displays success/failure inline

### 4. Query Execution UI

A panel for executing queries against a workflow's current state.

- **Query selector**: Dropdown of registered query methods
- **Parameters**: Dynamic form fields based on query method signature
- **Result display**: Formatted JSON output
- **History**: Keeps a log of recent queries in the session

### 5. Retry / Cancel / Terminate Controls

Action buttons on the workflow detail page.

- **Retry**: Re-enqueue a failed workflow from its last checkpoint
- **Cancel**: Request graceful cancellation (workflow can run cleanup)
- **Terminate**: Immediately halt execution (no cleanup)
- **Confirmation dialogs**: Each action requires explicit confirmation
- **Audit trail**: Actions are recorded as events in the workflow history

### 6. Real-Time Updates via SSE

Server-Sent Events endpoint that streams execution state changes.

- **Per-execution stream**: Subscribe to updates for a specific workflow execution
- **Global stream**: Subscribe to all execution state changes (for the list view)
- **Event types**: `status_changed`, `event_added`, `stats_updated`
- **Reconnection**: Automatic reconnect with last-event-ID support

### 7. Activity Detail Views

Expandable panels showing granular information about each activity.

- **Input/Output**: Full serialized input parameters and return values
- **Timing**: Scheduled at, started at, completed at, queue time, execution time
- **Retries**: Number of attempts, retry policy, error for each failed attempt
- **Worker info**: Which worker/process handled the activity (if tracked)

### 8. Compensation / Saga Visualization

Visual representation of saga compensation flows.

- **Activity chain**: Shows the forward execution path
- **Compensation markers**: Highlights which activities triggered compensation
- **Compensation chain**: Shows the reverse compensation path
- **Status per step**: Completed, compensated, failed-to-compensate
- **Directed graph view**: Optional DAG visualization for complex sagas

---

## Architecture

### API Endpoints

All endpoints are prefixed with `/api/chronos` and protected by the `ChronosModule` guard.

```
GET    /api/chronos/workflows
       ?status=running&type=OrderWorkflow&from=2026-01-01&to=2026-03-22
       &page=1&per_page=25&sort=started_at&order=desc&search=wf-abc

GET    /api/chronos/workflows/:id
       Returns full execution detail including event history

GET    /api/chronos/workflows/:id/events
       ?page=1&per_page=50
       Paginated event history (for executions with thousands of events)

POST   /api/chronos/workflows/:id/signal
       Body: { "signal": "approve", "payload": { ... } }

POST   /api/chronos/workflows/:id/query
       Body: { "query": "getBalance", "args": [ ... ] }

POST   /api/chronos/workflows/:id/retry

POST   /api/chronos/workflows/:id/cancel

POST   /api/chronos/workflows/:id/terminate

GET    /api/chronos/workflows/:id/activities/:activityId
       Detailed activity information

GET    /api/chronos/stats
       { "running": 12, "completed_today": 348, "failed": 3, "avg_duration_ms": 4520 }

GET    /api/chronos/stream
       SSE endpoint — global execution updates
       ?workflow_id=:id for per-execution stream
```

### Response Envelope

All JSON responses follow a consistent envelope:

```json
{
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 1042,
    "has_more": true
  }
}
```

Error responses:

```json
{
  "error": {
    "code": "WORKFLOW_NOT_FOUND",
    "message": "Workflow execution wf-abc-123 not found"
  }
}
```

### Backend Components

```
lattice/workflow
├── src/
│   └── Chronos/
│       ├── ChronosModule.php          # Module registration, route binding, guard config
│       ├── ChronosServiceProvider.php  # Service provider for standalone registration
│       ├── Http/
│       │   ├── WorkflowListController.php
│       │   ├── WorkflowDetailController.php
│       │   ├── WorkflowActionController.php
│       │   ├── QueryController.php
│       │   ├── ActivityController.php
│       │   ├── StatsController.php
│       │   └── StreamController.php   # SSE endpoint
│       ├── Guards/
│       │   └── ChronosGuard.php       # Admin-only access by default
│       └── Config/
│           └── chronos.php            # Guard config, route prefix, SSE settings
```

### Frontend Structure

```
projects/chronos/frontend/
├── src/
│   ├── App.tsx (or .svelte)
│   ├── api/
│   │   ├── client.ts                  # Axios/fetch wrapper
│   │   ├── workflows.ts              # Workflow API calls
│   │   └── sse.ts                    # SSE connection manager
│   ├── components/
│   │   ├── Layout/
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Header.tsx
│   │   │   └── Breadcrumbs.tsx
│   │   ├── Workflows/
│   │   │   ├── WorkflowTable.tsx
│   │   │   ├── WorkflowFilters.tsx
│   │   │   ├── StatusBadge.tsx
│   │   │   └── WorkflowActions.tsx
│   │   ├── Timeline/
│   │   │   ├── EventTimeline.tsx
│   │   │   ├── EventCard.tsx
│   │   │   └── EventDetail.tsx
│   │   ├── Activities/
│   │   │   └── ActivityPanel.tsx
│   │   ├── Signals/
│   │   │   └── SignalModal.tsx
│   │   ├── Queries/
│   │   │   └── QueryPanel.tsx
│   │   ├── Sagas/
│   │   │   └── CompensationGraph.tsx
│   │   └── Stats/
│   │       ├── StatsBar.tsx
│   │       └── DurationChart.tsx
│   ├── pages/
│   │   ├── DashboardPage.tsx
│   │   ├── WorkflowListPage.tsx
│   │   └── WorkflowDetailPage.tsx
│   ├── hooks/ (or stores/)
│   │   ├── useWorkflows.ts
│   │   ├── useSSE.ts
│   │   └── useTheme.ts
│   └── styles/
│       └── tailwind.css
├── index.html
├── vite.config.ts
├── tailwind.config.js
├── tsconfig.json
└── package.json
```

---

## Dependencies

| Package                | Role                                               |
|------------------------|----------------------------------------------------|
| `lattice/workflow`     | Core workflow engine — Chronos API lives here       |
| `lattice/workflow-store` | Persistence layer — reads execution and event data |
| `lattice/http`         | HTTP routing, middleware, streaming responses        |

### Optional / Future

| Package              | Role                                          |
|----------------------|-----------------------------------------------|
| `lattice/auth`       | If using LatticePHP's auth system for guards   |
| `lattice/ripple`  | (optional — for WebSocket real-time instead of SSE) |

---

## Design Inspiration

### Temporal Web UI
- Event history timeline layout
- Workflow execution detail structure
- Query and signal interaction patterns
- Status filtering and search UX

### Laravel Horizon
- Design language and visual style (clean, dashboard-oriented)
- Stats bar with real-time counters
- Dark theme by default
- Sidebar navigation pattern
- "PHP ecosystem" feel — familiar to the target audience

### Key Design Principles
- **Instant clarity**: The dashboard should answer "what's happening right now?" in under 2 seconds
- **Drill-down depth**: From overview to individual event payload in 2 clicks
- **Operational confidence**: Retry/cancel/terminate actions must feel deliberate (confirmation dialogs, clear consequences)
- **Real-time by default**: No manual refresh — SSE keeps everything current
- **Dark-first**: Default to dark theme (developer tool), with light theme available

---

## Open Questions

1. **React vs Svelte**: React has broader hiring pool and ecosystem; Svelte produces smaller bundles and simpler code. Recommend prototyping Phase 2 scaffolding in both before committing.
2. **Embedded asset bundling**: Need a strategy for shipping compiled frontend assets inside the Composer package (similar to how Laravel Horizon ships its compiled assets in `public/vendor/horizon`).
3. **Multi-tenancy**: Should Chronos support namespace/tenant filtering if `lattice/workflow` supports multi-tenancy?
4. **Retention policy**: Should the stats endpoint respect a configurable data retention window, or always query all data?
5. **RBAC granularity**: Current design is admin-only. Should there be read-only vs operator roles (read-only can view but not signal/cancel)?
