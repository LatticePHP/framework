# ADR-005: Runtime Support

**Date:** 2026-03-21
**Status:** Accepted

## Context

PHP applications can run under several execution models, each with different characteristics for performance, concurrency, and long-running process support:

- **PHP-FPM:** The traditional model. Each request boots the application, handles the request, and tears down. Simple, battle-tested, widely deployed. No persistent state between requests.
- **RoadRunner:** A Go-based application server that keeps PHP workers alive between requests. Supports HTTP, gRPC, queues, cron, and more. Workers maintain state across requests, enabling connection pooling, preloaded containers, and long-running processes.
- **OpenSwoole (formerly Swoole):** A PHP extension providing async I/O, coroutines, and an event loop. Highest potential performance but requires careful state management and has a smaller ecosystem.

LatticePHP's focus on APIs, workers, and durable workflows makes long-running process support essential. However, the framework must remain accessible to the vast majority of PHP deployments that use FPM.

## Decision

### Runtime Tiers

| Runtime | Tier | Description |
|---------|------|-------------|
| **PHP-FPM** | Baseline | Every feature must work correctly on FPM. No feature may require a long-running process. |
| **RoadRunner** | First-class | Optimized integration with dedicated `lattice/roadrunner` package. Preloaded containers, persistent connections, native gRPC, queue workers. |
| **OpenSwoole** | Experimental | Community-supported via `lattice/openswoole` package. Coroutine-aware connection pools, async I/O. Not guaranteed stable until v2. |

### Runtime Abstraction

The framework will provide a `RuntimeInterface` contract that abstracts runtime-specific behavior:

- Worker lifecycle management (boot, handle, reset).
- Connection persistence and pooling.
- Concurrency primitives (where available).
- Graceful shutdown handling.

### FPM Compatibility Rules

1. All framework features must function correctly on PHP-FPM.
2. Long-running optimizations (persistent connections, preloaded state) are enhancements, not requirements.
3. Queue workers and schedulers run as separate CLI processes, not embedded in the HTTP runtime.
4. Durable workflow replay works identically on all runtimes.

### RoadRunner Integration

The `lattice/roadrunner` package provides:

- HTTP worker with request/response marshaling.
- gRPC worker integration.
- Queue consumer using RoadRunner's Jobs plugin.
- Metrics export via RoadRunner's Prometheus plugin.
- Container state reset between requests to prevent memory leaks.
- Pre-configured `.rr.yaml` in starters.

## Consequences

**Positive:**
- No developer is locked out. FPM works everywhere, including shared hosting and basic Docker deployments.
- RoadRunner as first-class gives a clear performance upgrade path without changing application code.
- The runtime abstraction allows future runtimes (FrankenPHP, etc.) to be added as packages.
- Workflow workers benefit significantly from RoadRunner's persistent process model.

**Negative:**
- Maintaining FPM compatibility constrains some design choices (no reliance on persistent in-memory state for core features).
- Two runtimes to test in CI, three once OpenSwoole stabilizes.
- RoadRunner requires Go binary distribution, which may complicate some deployment pipelines.

## Alternatives Considered

1. **RoadRunner only:** Would provide the best developer experience and performance but exclude the majority of PHP deployments. Too aggressive for a new framework.

2. **FPM only:** Would limit the framework's ability to compete on performance and restrict features like gRPC and efficient queue workers. Does not align with the framework's ambitious goals.

3. **FrankenPHP as first-class:** FrankenPHP (Caddy-based) is promising but less mature than RoadRunner for the specific features LatticePHP needs (gRPC, job queues, cron). Can be added as a first-class runtime later.

4. **Swoole instead of OpenSwoole:** The Swoole/OpenSwoole ecosystem has fragmentation and licensing concerns. OpenSwoole was chosen for its more open governance, but both are marked experimental given the ecosystem's instability.
