# ADR-011: Transport Matrix

**Date:** 2026-03-21
**Status:** Accepted

## Context

LatticePHP supports multiple communication transports for different use cases: synchronous HTTP APIs, durable workflows, gRPC services, and asynchronous messaging through various brokers. Building all transports simultaneously would spread resources too thin. A prioritized rollout ensures the highest-value transports ship first and receive the most testing.

## Decision

### Transport Priority Order

| Priority | Transport | Target Release | Rationale |
|----------|-----------|---------------|-----------|
| 1 | **HTTP (REST/JSON)** | MVP | Core use case. Every API project needs this. |
| 2 | **Native Workflows** | MVP | Key differentiator. Must ship early to validate positioning. |
| 3 | **gRPC** | v1.0 | Essential for microservice communication. RoadRunner provides native gRPC support. |
| 4 | **NATS** | v1.0 | Lightweight, high-performance messaging. Ideal first broker for microservices. |
| 5 | **RabbitMQ** | v1.0 | Most widely deployed message broker in PHP ecosystem. |
| 6 | **SQS** | v1.1 | AWS-native queue. Important for cloud deployments. |
| 7 | **Kafka** | v1.2 | High-throughput event streaming. Complex to integrate well. |

### MVP Scope (Phase 1-3)

- Full HTTP layer with routing, validation, OpenAPI, Problem Details.
- Native durable workflow engine with database + queue backend.
- Queue system with database and Redis backends (for activities and jobs).

### v1.0 Additions

- gRPC server and client via RoadRunner integration.
- NATS transport for lightweight pub/sub and request/reply.
- RabbitMQ transport for traditional message queuing.

### v1.1+ Additions

- SQS transport for AWS-native deployments.
- Kafka transport for high-throughput event streaming.
- Additional transports based on community demand.

### Transport Abstraction

All message brokers implement a common `TransportInterface`:

```php
interface TransportInterface {
    public function send(Envelope $envelope): void;
    public function receive(): iterable; // yields Envelope objects
    public function ack(Envelope $envelope): void;
    public function reject(Envelope $envelope, bool $requeue = false): void;
}
```

This ensures that switching between brokers requires only configuration changes, not code changes.

### Queue Backends for Jobs and Activities

The `lattice/queue` package supports multiple backends independently of message transports:

| Backend | Use Case |
|---------|----------|
| Database | Default. Zero additional infrastructure. Suitable for low-to-medium throughput. |
| Redis | Higher throughput, lower latency. Common in PHP deployments. |
| RoadRunner Jobs | Native integration when running on RoadRunner. |
| SQS | Cloud-native deployments. |

## Consequences

**Positive:**
- MVP ships with the two most important capabilities (HTTP APIs and durable workflows).
- Each transport is thoroughly tested before the next one begins.
- The transport abstraction prevents vendor lock-in for messaging.
- Priority order matches market demand and user expectations.

**Negative:**
- Teams needing gRPC or message brokers from day one must wait for v1.0.
- NATS before RabbitMQ may surprise teams with existing RabbitMQ infrastructure (but NATS is simpler to implement and test).
- Kafka last means high-throughput streaming use cases are not addressed until v1.2.

## Alternatives Considered

1. **Ship all transports in MVP:** Would delay the MVP significantly and spread quality thin. Better to ship fewer transports with excellent quality.

2. **RabbitMQ before NATS:** RabbitMQ has more PHP ecosystem usage, but NATS is simpler to implement, has a lighter operational footprint, and serves as a better validation of the transport abstraction.

3. **Kafka before SQS:** Kafka is more technically interesting but SQS is simpler to integrate and serves the large AWS-deployed PHP user base.

4. **Skip gRPC entirely:** Would undermine the microservices positioning. gRPC is essential for efficient service-to-service communication and RoadRunner makes it feasible with minimal effort.
