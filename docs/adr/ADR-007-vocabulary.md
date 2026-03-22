# ADR-007: Vocabulary

**Date:** 2026-03-21
**Status:** Accepted

## Context

A framework's vocabulary shapes how developers think about and discuss their applications. Inconsistent or ambiguous terminology leads to confusion in documentation, code reviews, and community discussions. LatticePHP draws inspiration from multiple sources (NestJS, Temporal, Laravel, Symfony) and must establish a canonical vocabulary that is used consistently across all code, documentation, CLI generators, and error messages.

## Decision

The following terms are the official vocabulary for LatticePHP. All framework code, documentation, generators, and community resources must use these terms consistently.

### Module System

| Term | Definition |
|------|-----------|
| **Module** | A self-contained unit of functionality with declared imports, exports, providers, and controllers. The primary organizational boundary. |
| **Provider** | A class registered in the DI container that can be injected as a dependency. Services, repositories, and factories are all providers. |
| **Controller** | A class that handles incoming requests (HTTP, gRPC, or message) and returns responses. Entry point for request processing. |
| **Guard** | A class that determines whether a request should proceed (authentication/authorization). Returns true/false. Runs before the controller. |
| **Pipe** | A class that transforms or validates input data before it reaches the controller. Used for parsing, validation, and transformation. |
| **Interceptor** | A class that wraps controller execution with before/after logic. Used for logging, caching, response mapping, and timing. |
| **Filter** | A class that handles exceptions thrown during request processing. Maps exceptions to appropriate error responses. |
| **ExecutionContext** | An object providing metadata about the current execution (HTTP request, gRPC call, queue job, workflow activity). Enables runtime-agnostic guards and interceptors. |

### Workflow Engine

| Term | Definition |
|------|-----------|
| **Workflow** | A durable, long-running orchestration defined as a PHP class. Survives process restarts through deterministic replay. |
| **Activity** | A unit of work within a workflow that performs side effects (API calls, database writes, file operations). Activities are the only place side effects are allowed. |
| **Signal** | An external input sent to a running workflow instance to communicate events or trigger state changes. |
| **Query** | A read-only request to inspect a running workflow's current state without affecting its execution. |
| **Update** | A request that both mutates workflow state and returns a result. Combines signal + query semantics. |
| **Compensation** | A rollback activity invoked when a workflow or saga needs to undo previously completed steps. |
| **Replay** | The process of rebuilding workflow state by re-executing the workflow function against its recorded event history. |

### Async / Messaging

| Term | Definition |
|------|-----------|
| **Job** | A short-lived unit of background work dispatched to a queue. Fire-and-forget or with result tracking. |
| **Message** | A structured payload sent through a message broker (NATS, RabbitMQ, Kafka, SQS). Can be a command or event. |
| **Event** | Something that happened. Published to notify zero or more listeners. No return value expected. |
| **Command** | An instruction to do something. Dispatched to exactly one handler. May return a result. |

### Terms to Avoid

| Avoid | Use Instead | Reason |
|-------|-------------|--------|
| Service | Provider | "Service" is overloaded. "Provider" is precise about DI registration. |
| Middleware | Pipe, Interceptor, or Guard | We distinguish between these three specific roles rather than using one generic term. |
| Handler | Controller (for requests), Activity (for workflow steps) | "Handler" is ambiguous about what is being handled. |
| Task | Job (background), Activity (workflow) | "Task" is too generic. |
| Listener | Event Listener (explicit) | Always qualify with "Event" to distinguish from other listener patterns. |

## Consequences

**Positive:**
- Unambiguous communication across code, docs, and community.
- New developers learn one set of terms that applies everywhere.
- CLI generators can use consistent naming (`lattice make:guard`, `lattice make:pipe`).
- Error messages reference terms that map directly to documentation sections.

**Negative:**
- Developers coming from Laravel must learn new terms (Middleware becomes Guard/Pipe/Interceptor).
- Developers coming from Symfony must remap some concepts (EventSubscriber becomes Event Listener).
- The "Provider" term may initially confuse Laravel developers who associate it with "ServiceProvider" (a different concept).

## Alternatives Considered

1. **Use Laravel vocabulary (Middleware, ServiceProvider, etc.):** Would ease migration but lose the precision of distinguishing Guards from Pipes from Interceptors. These are architecturally distinct concepts that deserve distinct names.

2. **Use Symfony vocabulary (Bundle, EventSubscriber, Voter, etc.):** Would confuse the market positioning. LatticePHP is not a Symfony derivative.

3. **Invent entirely new terms:** Unnecessary cognitive load. Where existing terms from NestJS or Temporal are well-established, we adopt them rather than inventing alternatives.
