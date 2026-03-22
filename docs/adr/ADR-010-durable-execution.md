# ADR-010: Native Durable Execution Engine

**Date:** 2026-03-21
**Status:** Accepted

## Context

Durable execution -- the ability for long-running processes to survive failures, restarts, and deployments without losing progress -- is a critical capability for business-critical workflows. Systems like Temporal, AWS Step Functions, and Azure Durable Functions provide this, but they all require external infrastructure that PHP applications do not typically run.

Temporal, while powerful, requires:
- A Temporal Server cluster (Go binary).
- A persistence backend (Cassandra, MySQL, or PostgreSQL for Temporal itself).
- Separate operational monitoring and maintenance.
- A PHP SDK that communicates with the Temporal Server via gRPC.

This is a significant infrastructure burden, especially for teams that already have a database and a queue system and just want durable workflows without adding new operational dependencies.

LatticePHP's users need Temporal-like semantics -- deterministic replay, event history, signals, queries, compensation -- but built natively using infrastructure they already have: a relational database and a queue.

## Decision

LatticePHP will build a **native durable execution engine** with no external dependencies beyond a relational database and a queue system. The engine provides Temporal-like semantics implemented entirely in PHP.

### Core Architecture

```
+-------------------+     +-------------------+     +-------------------+
|   Workflow Code   | --> | Workflow Runtime   | --> |  Event Store (DB) |
|   (Deterministic) |     | (Replay Engine)   |     |  workflow_events  |
+-------------------+     +-------------------+     +-------------------+
                                |                           |
                                v                           |
                     +-------------------+                  |
                     |   Queue System    | <--- Activity results recorded
                     | (Activity Dispatch)|
                     +-------------------+
                                |
                                v
                     +-------------------+
                     | Activity Workers  |
                     | (Side Effects OK) |
                     +-------------------+
```

### Database Schema

**`workflow_executions` table:**
- `id` (UUID) -- unique execution identifier
- `workflow_type` -- fully qualified workflow class name
- `status` (enum: pending, running, paused, completed, failed, cancelled, timed_out)
- `input` (JSON) -- serialized workflow input
- `result` (JSON) -- serialized workflow output (on completion)
- `error` (JSON) -- error details (on failure)
- `parent_id` (UUID, nullable) -- for child workflows
- `started_at`, `completed_at`, `updated_at` timestamps
- `retry_count`, `max_retries`

**`workflow_events` table:**
- `id` (BIGINT, auto-increment) -- event sequence number
- `execution_id` (UUID) -- foreign key to workflow_executions
- `event_type` (enum: workflow_started, activity_scheduled, activity_completed, activity_failed, timer_scheduled, timer_fired, signal_received, query_received, workflow_completed, workflow_failed, child_workflow_started, child_workflow_completed, compensation_started, compensation_completed)
- `event_data` (JSON) -- type-specific payload
- `timestamp` -- when the event occurred
- `sequence` -- monotonically increasing per execution, used for replay ordering

### Deterministic Replay

The core of the engine is deterministic replay:

1. When a workflow needs to advance, the runtime loads all events for that execution from the `workflow_events` table.
2. The workflow function is re-executed from the beginning.
3. For each side-effecting operation (activity, timer, signal wait), the runtime checks if a matching event exists in the history:
   - **If yes:** Return the recorded result without executing the operation again.
   - **If no:** This is a new operation. Schedule it (dispatch activity to queue, register timer) and pause the workflow.
4. When the scheduled operation completes, its result is recorded as a new event, and replay resumes.

### Determinism Enforcement

Workflow code must be deterministic. The runtime enforces this by:
- Prohibiting direct I/O, database calls, HTTP requests, or filesystem access in workflow code (these must go through activities).
- Providing deterministic replacements: `$ctx->now()` instead of `new DateTime()`, `$ctx->uuid()` instead of `Uuid::v4()`.
- Detecting non-determinism during replay (mismatched event types) and failing with a clear error.

### Activities

Activities are the only place where side effects are allowed:

- Each activity is dispatched as a job on the queue system.
- Activity workers pick up and execute the activity.
- Results (success or failure) are recorded in the `workflow_events` table.
- The workflow is then re-triggered for replay, which will now find the activity result in history and continue.

### Signals and Queries

- **Signals:** Written to the `workflow_events` table as `signal_received` events. The workflow is re-triggered for replay, which processes the signal.
- **Queries:** Replay the workflow to current state, then call the query handler to return the requested data. No new events are written.
- **Updates:** Replay to current state, execute the update handler (which may produce new events), return the result.

### Timers

- Timer requests are recorded in the events table and registered with the scheduler (`lattice/scheduler`).
- When the timer fires, a `timer_fired` event is written and the workflow is re-triggered.
- For FPM deployments, timers are polled by a background worker process.

### Compensation (Saga Pattern)

Workflows can register compensation handlers for completed activities:

```php
$reserved = $ctx->activity(ReserveInventory::class, $order);
$ctx->onCompensate(fn() => $ctx->activity(ReleaseInventory::class, $reserved));

$charged = $ctx->activity(ChargePayment::class, $order);
$ctx->onCompensate(fn() => $ctx->activity(RefundPayment::class, $charged));
```

If the workflow fails or is explicitly cancelled, compensation handlers execute in reverse order.

### Child Workflows

Workflows can spawn child workflows with independent event histories. The parent records `child_workflow_started` and `child_workflow_completed` events.

## Consequences

**Positive:**
- Zero external infrastructure beyond database + queue, which every PHP application already has.
- The framework owns the entire execution stack -- no version mismatches with external services.
- Can be optimized for PHP-specific patterns (serialization, memory management, process lifecycle).
- Developers debug workflows using standard PHP tooling (Xdebug, var_dump, logging).
- Workflow state is inspectable via standard database queries.
- Simpler deployment: no separate Temporal server to manage, monitor, and upgrade.
- Works on all runtimes (FPM, RoadRunner, OpenSwoole) without modification.

**Negative:**
- Significant engineering effort to build correctly. Deterministic replay is subtle and error-prone.
- Less battle-tested than Temporal, which has years of production use at scale.
- Performance ceiling is lower than Temporal for very high throughput (thousands of workflows per second).
- Must implement our own history compaction, archival, and scaling strategies.
- Risk of subtle bugs in the replay engine that could corrupt workflow state.

### Mitigations

- Extensive property-based testing of the replay engine.
- Comprehensive test suite covering all event type combinations.
- Event history versioning to support workflow code changes.
- Optional Temporal adapter package (`lattice/temporal-bridge`) for teams that need Temporal's scale.

## Alternatives Considered

1. **Use Temporal as primary engine:** Powerful but adds significant operational burden. Most LatticePHP users will not have a Temporal cluster. Could be offered as an optional adapter for teams that need it.

2. **Use database only (no queue):** Simpler but would require polling for activity execution, resulting in higher latency and database load. Queues provide efficient push-based activity dispatch.

3. **Use event sourcing library (e.g., EventSauce):** Event sourcing is a building block, not a workflow engine. Would still need to build replay, signals, queries, timers, and compensation on top. Starting from purpose-built tables is more direct.

4. **State machine approach (no replay):** Simpler but loses the ability to define workflows as linear PHP code. State machines become unwieldy for complex business processes with many branches and compensations.

5. **AWS Step Functions / Azure Durable Functions integration:** Cloud-vendor lock-in. LatticePHP must be cloud-agnostic.
