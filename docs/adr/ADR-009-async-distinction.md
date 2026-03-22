# ADR-009: Async Layer Distinction

**Date:** 2026-03-21
**Status:** Accepted

## Context

Many frameworks blur the lines between background jobs, distributed messaging, and workflow orchestration. Laravel, for example, uses the same queue system for simple jobs, event broadcasting, and complex multi-step processes. This conflation leads to architectural confusion:

- Developers build fragile multi-step processes using chained jobs when they need workflows.
- Simple fire-and-forget tasks get over-engineered with messaging infrastructure.
- Distributed event systems get squeezed into job queue semantics.

LatticePHP must provide clear, distinct abstractions for each async pattern, with separate mental models, APIs, and documentation.

## Decision

LatticePHP defines three distinct async layers:

### Layer 1: Jobs (Short Background Work)

**Package:** `lattice/queue`

**Purpose:** Offload short, discrete units of work from the request cycle.

**Characteristics:**
- Fire-and-forget or with optional result tracking.
- Single attempt with configurable retries.
- No built-in state persistence between attempts beyond the serialized payload.
- Typical duration: milliseconds to minutes.
- Backends: database, Redis, RoadRunner Jobs, SQS.

**Examples:** Send email, resize image, generate PDF, update search index.

```php
#[AsJob(queue: 'default', retries: 3)]
class SendWelcomeEmail {
    public function handle(User $user, Mailer $mailer): void { ... }
}
```

### Layer 2: Messages (Distributed Events and Commands)

**Package:** `lattice/events` + `lattice/transport-*`

**Purpose:** Decouple services through asynchronous event publishing and command dispatching across process/service boundaries.

**Characteristics:**
- Events: publish to zero or more subscribers (fan-out).
- Commands: dispatch to exactly one handler (point-to-point).
- Message brokers provide delivery guarantees, ordering, and replay.
- No orchestration. Each handler is independent.
- Backends: NATS, RabbitMQ, Kafka, SQS.

**Examples:** `OrderPlaced` event triggers inventory, billing, and notification services independently.

```php
#[AsEvent(transport: 'nats')]
class OrderPlaced {
    public function __construct(
        public readonly string $orderId,
        public readonly float $total,
    ) {}
}
```

### Layer 3: Workflows (Durable Orchestration)

**Package:** `lattice/workflow` + `lattice/workflow-store`

**Purpose:** Orchestrate multi-step, long-running business processes with durability, compensation, and observability.

**Characteristics:**
- Survives process restarts through deterministic replay.
- Full event history persisted in database.
- Supports signals (external input), queries (state inspection), and updates.
- Built-in compensation (saga pattern) for rollback scenarios.
- Typical duration: seconds to months.
- Activities dispatched as queue jobs with automatic result recording.

**Examples:** Order fulfillment (reserve inventory, charge payment, ship, notify), employee onboarding, subscription lifecycle.

```php
#[AsWorkflow]
class OrderFulfillment {
    public function execute(WorkflowContext $ctx, OrderData $order): OrderResult {
        $reserved = $ctx->activity(ReserveInventory::class, $order);
        $charged = $ctx->activity(ChargePayment::class, $order, $reserved);
        // ...
    }
}
```

### Decision Flowchart

1. Is it a single, independent unit of work? **Use a Job.**
2. Does it need to notify other services without orchestration? **Use a Message (Event).**
3. Does it need to instruct a specific handler across a service boundary? **Use a Message (Command).**
4. Does it involve multiple coordinated steps, need to survive failures, or run for extended periods? **Use a Workflow.**

## Consequences

**Positive:**
- Developers choose the right tool for each async pattern.
- Each layer has a focused, minimal API without overloaded abstractions.
- Workflows are not constrained by job queue semantics.
- Messaging does not carry workflow orchestration complexity.
- Documentation and tutorials can teach each concept independently.
- Migration from Laravel is gradual: start with Jobs (familiar), adopt Messages and Workflows as needed.

**Negative:**
- Three separate packages and concepts to learn (higher initial learning curve than a single "queue" abstraction).
- Some overlap at the edges (a Job and a single-step Workflow are functionally similar).
- Developers must make an explicit architectural choice rather than using one generic mechanism.

## Alternatives Considered

1. **Single unified async system:** Simpler to learn initially but inevitably leads to the "job queue as poor man's workflow engine" anti-pattern. The simplicity is deceptive.

2. **Jobs + Workflows only (no separate messaging):** Would require the workflow or job system to handle distributed events, conflating concerns. Message brokers have fundamentally different semantics (pub/sub, fan-out) than job queues.

3. **External orchestration only (Temporal, Step Functions):** Would add infrastructure requirements that conflict with LatticePHP's zero-external-dependency goal for workflows (see ADR-010).
