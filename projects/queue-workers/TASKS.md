# Queue Workers — Task List

## ActivityJob Implementation

- [ ] Create `ActivityJob` class implementing the queue job interface
- [ ] Implement activity class resolution and instantiation within the job
- [ ] Implement activity input deserialization
- [ ] Implement activity output serialization
- [ ] Write `ActivityCompleted` event to workflow event store on success
- [ ] Write `ActivityFailed` event to workflow event store on failure
- [ ] Include workflow ID, activity ID, and attempt number in job metadata
- [ ] Add retry configuration (max attempts, backoff strategy) to ActivityJob
- [ ] Add activity execution timeout at the job level

## WorkflowContext Integration

- [ ] Refactor `WorkflowContext::executeActivity()` to dispatch `ActivityJob` instead of calling inline
- [ ] Return a pending result handle from `executeActivity()` that the workflow can await
- [ ] Support `executeActivity()` with async/deferred semantics for parallel activity dispatch
- [ ] Preserve existing synchronous mode as a configurable fallback (for testing/dev)
- [ ] Pass activity options (timeout, retry policy, queue name) through to the job

## Result Awaiting

- [ ] Implement event store polling for `ActivityCompleted` / `ActivityFailed` events
- [ ] Support configurable poll interval
- [ ] Implement event-driven notification as alternative to polling (where transport supports it)
- [ ] Handle result deserialization back into workflow context
- [ ] Handle multiple concurrent pending activities (parallel execution)
- [ ] Ensure workflow state is correctly persisted between activity dispatches

## Timeout Handling

- [ ] Implement orchestrator-side activity timeout enforcement
- [ ] Write `ActivityTimedOut` event when timeout is exceeded
- [ ] Trigger workflow failure/compensation handler on timeout
- [ ] Support per-activity timeout configuration
- [ ] Support workflow-level global activity timeout default
- [ ] Cancel/abandon timed-out jobs if the queue driver supports it

## Failure Propagation

- [ ] Propagate activity exception type and message to the workflow
- [ ] Support configurable retry-before-fail policy (e.g., retry 3x then fail)
- [ ] Record each attempt in the event store for debugging
- [ ] Trigger compensation/rollback activities on unrecoverable failure
- [ ] Support `continueOnFailure` option for non-critical activities

## Queue Driver Compatibility

- [ ] Test with database queue driver end-to-end
- [ ] Test with Redis queue driver end-to-end
- [ ] Verify job serialization/deserialization works across driver boundaries
- [ ] Test worker restart and job recovery
- [ ] Test concurrent workers processing activities for the same workflow

## Unit Tests

- [ ] Unit test: ActivityJob serialization/deserialization of activity parameters
- [ ] Unit test: ActivityJob event writing (ActivityScheduled, ActivityCompleted, ActivityFailed)
- [ ] Unit test: ActivityJob error handling and failure propagation
- [ ] Unit test: Result awaiting timeout behavior
- [ ] Unit test: ActivityJob retry logic

## Stress Tests & Benchmarks

- [ ] Update existing workflow stress tests to use queue-based execution
- [ ] Benchmark queue-based vs. synchronous activity execution
- [ ] Test workflow with 50+ sequential activities via queue
- [ ] Test workflow with 20+ parallel activities via queue
- [ ] Test under high queue backpressure (slow consumers)
- [ ] Test with mixed fast and slow activities

## Documentation

- [ ] Document queue worker setup and configuration
- [ ] Document activity timeout and retry configuration
- [ ] Document how to run workflow workers (`php lattice queue:work --queue=activities`)
- [ ] Add architecture diagram showing orchestrator/worker interaction
- [ ] Document migration path from synchronous to queue-based activities
- [ ] Update workflow package README with queue worker section
