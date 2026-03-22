# Queue Workers — Async Workflow Activity Execution

## Overview

Wire workflow activities to execute via queue jobs instead of running synchronously inline. Currently, `WorkflowContext::executeActivity()` calls the activity directly in the same process. This project introduces an `ActivityJob` that dispatches the activity to a queue, waits for the result via the event store, and propagates success or failure back to the workflow.

## Problem

When activities run synchronously, a single workflow execution blocks a process for the entire duration of all its activities. Long-running activities (HTTP calls, file processing, external API calls) stall the workflow worker. Moving activity execution to queue jobs enables:

- **Parallelism**: Multiple activities can execute concurrently across different workers.
- **Resilience**: Failed activities are retried by the queue infrastructure.
- **Scalability**: Activity workers scale independently from workflow orchestrators.

## Architecture

1. `WorkflowContext::executeActivity()` dispatches an `ActivityJob` to the configured queue.
2. The `ActivityJob` executes the activity and writes an `ActivityCompleted` or `ActivityFailed` event to the workflow event store.
3. The workflow orchestrator polls/subscribes to the event store, picks up the result, and resumes the workflow.
4. Timeouts are enforced by the orchestrator — if no result arrives within the configured timeout, an `ActivityTimedOut` event is recorded and the workflow's failure handler is invoked.

## Success Criteria

1. Activities execute in a separate queue worker process, not inline.
2. Workflow correctly resumes after activity completion.
3. Activity failures propagate to the workflow's error handling.
4. Timeouts are enforced and handled gracefully.
5. Stress tests pass with queue-based execution.
6. Works with both database and Redis queue drivers.

## Dependencies

| Package | Role |
|---|---|
| `lattice/workflow` | Workflow orchestrator, activity execution context |
| `lattice/workflow-store` | Event store for activity result persistence |
| `lattice/queue` | Job dispatch and worker infrastructure |
| `lattice/events` | Event propagation for activity completion |
