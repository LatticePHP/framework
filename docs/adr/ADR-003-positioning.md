# ADR-003: Positioning

**Date:** 2026-03-21
**Status:** Accepted

## Context

The PHP framework ecosystem is crowded. Laravel dominates full-stack development, Symfony provides enterprise-grade components, and API Platform targets API-centric projects. For LatticePHP to succeed, it must occupy a distinct and defensible position that clearly communicates its value to potential adopters.

LatticePHP's unique combination -- backend-only focus, deep modularity, API-first design, and native durable workflow orchestration -- does not exist in the current PHP ecosystem. This positioning must be codified early so that all decisions about features, documentation, marketing, and community building remain aligned.

## Decision

### One-Line Positioning Statement

> "A backend-only PHP framework with modular architecture, API-first design, and native durable workflow orchestration."

### Tagline

> "Modules. APIs. Workflows. No compromises."

### Key Differentiators

1. **Backend-only by design:** Not a full-stack framework with the frontend ripped out. Every architectural decision assumes no HTML rendering, no view layer, no asset pipeline.

2. **Modular to the core:** Not a monolith with optional packages. The module system is the foundation, with explicit dependency declarations, isolated providers, and clear boundaries.

3. **API-first, not API-also:** OpenAPI generation, Problem Details errors (RFC 9457), content negotiation, and API versioning are built into the core HTTP layer, not bolted on.

4. **Native durable workflows:** Temporal-like durable execution semantics (deterministic replay, event sourcing, signals, queries, compensation) built natively using database + queues. Zero external infrastructure beyond what PHP applications already use.

5. **Modern PHP:** Minimum PHP 8.4. Property hooks, asymmetric visibility, typed properties, enums, fibers, and attributes are used throughout -- not just supported.

### Target Audience

- Backend engineers building APIs and microservices in PHP.
- Teams migrating from Laravel/Symfony who need better workflow orchestration.
- Organizations running long-lived business processes that need durability guarantees.
- Platform teams providing internal services and gRPC endpoints.

### Non-Targets

- Full-stack web applications with server-rendered HTML.
- Content management systems.
- Rapid prototyping of CRUD apps with admin panels.
- WordPress/Drupal replacements.

## Consequences

**Positive:**
- Clear "reason to exist" that guides every framework decision.
- Easy for developers to determine if LatticePHP is right for their project.
- Marketing and documentation have a focused message.
- Feature requests outside the positioning can be respectfully declined.

**Negative:**
- Intentionally excludes a large segment of PHP developers (full-stack, CMS, etc.).
- Must deliver on the durable workflow promise -- it is a core differentiator, not a nice-to-have.

## Alternatives Considered

1. **Broader positioning as "modern PHP framework":** Too vague. Does not communicate what makes LatticePHP different from Laravel or Symfony.

2. **Positioning as "Temporal for PHP":** Too narrow. Workflows are a key differentiator but not the only one. This would undervalue the module system, API tooling, and runtime support.

3. **Positioning as "NestJS for PHP":** Useful for initial recognition but creates expectations of feature parity with a Node.js framework. LatticePHP should stand on its own identity.
