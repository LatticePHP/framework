# 02 — Queue Workers

> Wire workflow activities to execute via queue jobs instead of synchronously

## Dependencies
- None (Wave 1)
- Packages: `packages/workflow/`, `packages/workflow-store/`, `packages/queue/`

## Subtasks

### 1. [ ] Create ActivityJob class
- Create `ActivityJob` class implementing the queue job interface
- Implement activity class resolution and instantiation within the job
- Implement activity input deserialization and output serialization
- Write `ActivityScheduled` event to workflow event store on dispatch
- Write `ActivityCompleted` event on success, `ActivityFailed` event on failure
- Include workflow ID, activity ID, and attempt number in job metadata
- Add retry configuration (max attempts, backoff strategy) to ActivityJob
- Add activity execution timeout at the job level
- **Verify:** Unit test — ActivityJob serializes/deserializes correctly, events are written

### 2. [ ] Wire WorkflowContext::executeActivity to dispatch
- Refactor `WorkflowContext::executeActivity()` to dispatch `ActivityJob` instead of calling inline
- Return a pending result handle from `executeActivity()` that the workflow can await
- Support `executeActivity()` with async/deferred semantics for parallel activity dispatch
- Preserve existing synchronous mode as a configurable fallback (`workflow.activity_driver = 'queue'|'sync'`)
- Pass activity options (timeout, retry policy, queue name) through to the job
- **Verify:** Unit test — executeActivity dispatches job when driver=queue, runs inline when driver=sync

### 3. [ ] Implement result awaiting
- Implement event store polling for `ActivityCompleted` / `ActivityFailed` events
- Support configurable poll interval (default 100ms)
- Implement event-driven notification as alternative to polling (where transport supports it)
- Handle result deserialization back into workflow context
- Handle multiple concurrent pending activities (parallel execution)
- Ensure workflow state is correctly persisted between activity dispatches
- Configurable timeout (default 300s)
- **Verify:** Unit test — result awaiting returns when ActivityCompleted appears

### 4. [ ] Handle timeout and failure
- Implement orchestrator-side activity timeout enforcement
- Write `ActivityTimedOut` event when timeout is exceeded
- Throw `WorkflowActivityTimeoutException` on timeout
- Support per-activity timeout configuration and workflow-level global timeout default
- Cancel/abandon timed-out jobs if the queue driver supports it
- Propagate activity exception type and message to the workflow
- Support configurable retry-before-fail policy (e.g., retry 3x then fail)
- Record each attempt in the event store for debugging
- Trigger compensation/rollback activities on unrecoverable failure
- Support `continueOnFailure` option for non-critical activities
- **Verify:** Unit test — timeout throws, failure propagates, compensation triggers

### 5. [ ] Unit tests for ActivityJob
- Serialization/deserialization of activity parameters
- Event writing (ActivityScheduled, ActivityCompleted, ActivityFailed)
- Error handling and failure propagation
- Timeout behavior
- Retry logic
- **Verify:** `phpunit --filter=ActivityJob` all green

### 6. [ ] Integration test with real queue driver
- Test with database queue driver end-to-end
- Test with Redis queue driver end-to-end
- Verify job serialization/deserialization works across driver boundaries
- Test worker restart and job recovery
- Test concurrent workers processing activities for the same workflow
- Run a full workflow with 3+ activities through queue
- **Verify:** Workflow completes successfully, all events in correct order

### 7. [ ] Update stress tests
- Update existing workflow stress tests to use queue-based execution
- Benchmark queue-based vs. synchronous activity execution
- Test workflow with 50+ sequential activities via queue
- Test workflow with 20+ parallel activities via queue
- Test under high queue backpressure (slow consumers)
- Test with mixed fast and slow activities
- **Verify:** All existing stress tests pass with both sync and queue drivers

### 8. [ ] Documentation
- Document queue worker setup and configuration
- Document activity timeout and retry configuration
- Document how to run workflow workers (`php lattice queue:work --queue=activities`)
- Add architecture diagram showing orchestrator/worker interaction
- Document migration path from synchronous to queue-based activities
- Update workflow package README with queue worker section
- **Verify:** Docs render correctly, examples are runnable

## Integration Verification
- [ ] Run full CRM workflow through queue
- [ ] Run existing test suite — no regressions
- [ ] `make test-suite S=Workflow` all green
- [ ] Verify replay still works after queue-based execution
