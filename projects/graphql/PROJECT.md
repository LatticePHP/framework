# GraphQL -- Attribute-Based GraphQL Module

**Package:** `lattice/graphql`

## Overview

An attribute-driven GraphQL module for LatticePHP that brings the same attribute-based philosophy used throughout the framework to GraphQL API development. Instead of writing schema SDL files, developers annotate PHP classes with `#[Query]`, `#[Mutation]`, `#[Subscription]`, `#[ObjectType]`, and `#[Field]` attributes. The schema is generated automatically at compile time from these annotations.

The module integrates fully with the LatticePHP module system, guards, pipes, and interceptors. Each module can contribute its own types and resolvers, and the GraphQL schema is assembled from all registered modules.

## Design Philosophy

- **Attributes over SDL**: Define your entire GraphQL schema using PHP 8 attributes. No separate `.graphql` schema files to maintain.
- **Type inference**: PHP types (scalars, enums, classes) map automatically to GraphQL types. Attributes provide overrides where the default inference is insufficient.
- **Framework integration**: Authentication guards, validation pipes, and interceptors work the same as they do for REST endpoints.
- **Module scoping**: Queries, mutations, and types belong to the module that declares them, keeping large applications organized.

## Dependencies

| Package | Role |
|---|---|
| `lattice/core` | Application container, service registration |
| `lattice/module` | Module registration and lifecycle |
| `lattice/pipeline` | Pipe integration for input validation |
| `lattice/http` | HTTP handling for the GraphQL endpoint |
| `lattice/compiler` | Attribute discovery and compilation |
| `lattice/events` | Required for subscriptions |
| `lattice/cache` | (optional, for persisted queries) |

## Inspiration

- **NestJS GraphQL** -- attribute-driven (decorator-driven) GraphQL with module integration, but in TypeScript.
- **Lighthouse PHP** -- powerful GraphQL for Laravel, but SDL-first. `lattice/graphql` takes the attribute-first approach instead.

## Tech

- **webonyx/graphql-php** as the underlying GraphQL execution engine. The attribute layer generates the schema objects that webonyx/graphql-php executes.

## Success Criteria

1. Developers can define a complete GraphQL API using only PHP attributes -- no SDL files required.
2. Schema is generated at boot time (and cacheable) from discovered attributes via the compiler.
3. N+1 queries are preventable via the built-in DataLoader pattern.
4. Subscriptions work over SSE transport.
5. GraphiQL playground is available at a configurable endpoint.
6. Guards, pipes, and interceptors integrate seamlessly with resolvers.
7. Full test suite with testing utilities for consumer applications.
