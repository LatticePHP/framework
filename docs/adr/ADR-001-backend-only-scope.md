# ADR-001: Backend-Only Scope

**Date:** 2026-03-21
**Status:** Accepted

## Context

Modern PHP frameworks like Laravel ship with extensive frontend tooling: Blade templating, Vite integration, Inertia.js adapters, Livewire, SSR support, and NPM-based asset pipelines. While these features serve full-stack applications well, they introduce substantial complexity and maintenance burden for teams building APIs, background services, workflow orchestrators, and microservices.

LatticePHP targets a specific audience: backend engineers building API-first services, distributed workers, durable workflows, and microservice architectures in PHP. These use cases have no need for HTML rendering, view layers, or frontend asset pipelines. Including such features would dilute focus, increase the attack surface, bloat the dependency tree, and send mixed signals about the framework's purpose.

The PHP ecosystem already has mature frontend solutions. Teams that need server-rendered HTML can pair LatticePHP with standalone templating libraries or use a different framework for that layer entirely.

## Decision

LatticePHP is a **backend-only** framework. The core framework and all first-party packages will not include:

- Template engines (no Blade, Twig, or equivalent)
- Server-side rendering (SSR) for JavaScript frameworks
- Frontend asset compilation or bundling (no Vite, Webpack, Mix)
- CSS/JS tooling of any kind
- View layers or HTML response helpers
- Inertia.js or Livewire equivalents

The framework targets these use cases exclusively:

- REST and GraphQL APIs
- gRPC services
- Background job workers
- Durable workflow orchestration
- Event-driven microservices
- Scheduled task runners
- CLI tools and daemons

## Consequences

**Positive:**
- Dramatically simpler core with fewer packages to maintain.
- Clearer positioning in the PHP ecosystem -- no ambiguity about what LatticePHP is for.
- Smaller dependency footprint for every project.
- Faster installation and bootstrapping.
- Security audits cover a smaller surface area.
- Documentation can focus entirely on backend patterns without context-switching to frontend concerns.

**Negative:**
- Some existing Laravel/Symfony packages that assume a view layer will be incompatible without adaptation.
- Teams needing a full-stack solution must pair LatticePHP with a separate frontend or use a different framework.
- Admin panels and dashboards require a decoupled SPA or third-party tool.

## Alternatives Considered

1. **Full-stack framework with optional frontend:** Would dilute the project's focus and require maintaining frontend tooling that competes with mature solutions like Next.js, Nuxt, and SvelteKit.

2. **Backend-first with optional Blade/Twig package:** Tempting compromise, but "optional" frontend packages tend to become expected dependencies, blurring the project's identity. If demand arises, this can be a community package rather than a first-party offering.

3. **Headless-only with JSON response helpers:** This is effectively what we chose -- the framework excels at producing structured data responses (JSON, Protocol Buffers, etc.) without any HTML rendering capability.
