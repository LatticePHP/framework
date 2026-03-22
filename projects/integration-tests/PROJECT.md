# Integration Tests — Close the Coverage Gap

## Overview

Raise LatticePHP integration test coverage from the current 34% to 80%+. Unit tests exist across most packages, but real-world integration tests — ones that spin up actual services (Redis, NATS, RabbitMQ, PostgreSQL, etc.) via Docker containers in CI — are largely absent. This project fills that gap package by package.

## Scope

Every package that interacts with an external service or relies on cross-package collaboration needs integration tests. Tests run against real services in Docker containers orchestrated by the CI pipeline (GitHub Actions).

## Success Criteria

1. Integration test coverage reaches 80%+ across the full monorepo.
2. CI pipeline includes a Docker Compose stack for all required services.
3. Every transport, auth flow, queue backend, and caching backend has at least one end-to-end integration test.
4. Tests are isolated, repeatable, and do not depend on external network access.

## Infrastructure

- **CI**: GitHub Actions with Docker Compose services
- **Services**: Redis, PostgreSQL, NATS, RabbitMQ, LocalStack (SQS), Kafka (via Redpanda), mock OAuth provider
- **Test runner**: PHPUnit with integration test suite configuration
