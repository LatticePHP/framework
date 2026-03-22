# Validation Report — Package composer.json Files

> Generated: 2026-03-22
> Validator: Subtask 1 of Task 01 — Packagist Release

## Summary

- **42 packages** validated in `packages/*/composer.json`
- **4 starters** validated in `starters/*/composer.json`
- **Result: ALL PASS** after fixes applied

---

## Issues Found and Fixed

### Systemic Issues (affected all 42 packages)

| Issue | Affected | Fix Applied |
|-------|----------|-------------|
| Missing `keywords` field | All 42 packages | Added relevant keywords per package |
| Missing `homepage` field | All 42 packages | Added `https://github.com/LatticePHP/framework` |
| Missing `support` field | All 42 packages | Added `issues` and `source` URLs |

### Dependency Version Issues (affected 24 packages)

Cross-package `lattice/*` dependencies used development-only version constraints (`dev-main`, `@dev`, `*`, `^0.1`) instead of release constraints.

| Package | Dependency | Old Version | New Version |
|---------|-----------|-------------|-------------|
| `lattice/cache` | `lattice/contracts` | `*` | `^1.0` |
| `lattice/compiler` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/core` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/database` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/grpc` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/http` | `lattice/contracts` | `@dev` | `^1.0` |
| `lattice/http` | `lattice/routing` | `@dev` | `^1.0` |
| `lattice/http` | `lattice/database` | `@dev` | `^1.0` |
| `lattice/http` | `lattice/validation` | `@dev` | `^1.0` |
| `lattice/mail` | `lattice/contracts` | `*` | `^1.0` |
| `lattice/module` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/module` | `lattice/compiler` | `dev-main` | `^1.0` |
| `lattice/notifications` | `lattice/contracts` | `*` | `^1.0` |
| `lattice/notifications` | `lattice/mail` | `*` | `^1.0` |
| `lattice/observability` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/pipeline` | `lattice/contracts` | `*` | `^1.0` |
| `lattice/queue` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/rate-limit` | `lattice/contracts` | `@dev` | `^1.0` |
| `lattice/roadrunner` | `lattice/contracts` | `@dev` | `^1.0` |
| `lattice/serializer` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/testing` | `lattice/contracts` | `@dev` | `^1.0` |
| `lattice/transport-kafka` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/transport-nats` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/transport-rabbitmq` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/transport-sqs` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/validation` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/workflow` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/workflow-store` | `lattice/contracts` | `dev-main` | `^1.0` |
| `lattice/workflow-store` | `lattice/workflow` | `dev-main` | `^1.0` |
| `lattice/api-key` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/auth` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/authorization` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/authorization` | `lattice/auth` | `^0.1` | `^1.0` |
| `lattice/jwt` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/jwt` | `lattice/auth` | `^0.1` | `^1.0` |
| `lattice/microservices` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/oauth` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/pat` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/problem-details` | `lattice/contracts` | `^0.1` | `^1.0` |
| `lattice/social` | `lattice/contracts` | `^0.1` | `^1.0` |

### Path Repository Removal (affected 18 packages)

These packages had `repositories` entries with `type: path` pointing to sibling directories (e.g., `../contracts`). These are for local monorepo development only and must not be present in published packages.

Removed from: `cache`, `compiler`, `core`, `database`, `grpc`, `http` (4 repos), `mail`, `notifications` (2 repos), `observability`, `pipeline`, `queue`, `rate-limit`, `roadrunner`, `testing`, `transport-kafka`, `transport-nats`, `transport-rabbitmq`, `transport-sqs`, `validation`

### Minimum Stability Removal (affected 18 packages)

Removed `minimum-stability: dev` and `prefer-stable: true` from: `cache`, `compiler`, `core`, `database`, `grpc`, `http`, `mail`, `notifications`, `observability`, `pipeline`, `queue`, `roadrunner`, `testing`, `transport-kafka`, `transport-nats`, `transport-rabbitmq`, `transport-sqs`, `validation`

These settings are only needed for local development with path repositories and are not appropriate for published library packages.

### Starter Issues Fixed

| Starter | Issues Fixed |
|---------|-------------|
| `lattice/starter-api` | Added `keywords`, `homepage`, `support`; changed all 20 `lattice/*` deps from `dev-main` to `^1.0` |
| `lattice/starter-grpc` | Added `keywords`, `homepage`, `support`, `require-dev`, `autoload-dev`, `scripts`; changed 6 deps from `dev-main` to `^1.0` |
| `lattice/starter-service` | Added `keywords`, `homepage`, `support`, `require-dev`, `autoload-dev`, `scripts`; changed 8 deps from `dev-main` to `^1.0` |
| `lattice/starter-workflow` | Added `keywords`, `homepage`, `support`, `require-dev`, `autoload-dev`, `scripts`; changed 7 deps from `dev-main` to `^1.0` |

---

## Post-Fix Validation Results

### All 42 Packages

| # | Package | Description | Autoload | Autoload-dev | Require | Keywords | Homepage | Support | Issues |
|---|---------|-------------|----------|-------------|---------|----------|----------|---------|--------|
| 1 | `lattice/api-key` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 2 | `lattice/auth` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 3 | `lattice/authorization` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 4 | `lattice/cache` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 5 | `lattice/compiler` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 6 | `lattice/contracts` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 7 | `lattice/core` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 8 | `lattice/database` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 9 | `lattice/devtools` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 10 | `lattice/events` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 11 | `lattice/filesystem` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 12 | `lattice/grpc` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 13 | `lattice/http-client` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 14 | `lattice/http` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 15 | `lattice/jsonapi` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 16 | `lattice/jwt` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 17 | `lattice/mail` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 18 | `lattice/microservices` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 19 | `lattice/module` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 20 | `lattice/notifications` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 21 | `lattice/oauth` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 22 | `lattice/observability` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 23 | `lattice/openapi` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 24 | `lattice/openswoole` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 25 | `lattice/pat` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 26 | `lattice/pipeline` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 27 | `lattice/problem-details` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 28 | `lattice/queue` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 29 | `lattice/rate-limit` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 30 | `lattice/roadrunner` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 31 | `lattice/routing` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 32 | `lattice/scheduler` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 33 | `lattice/serializer` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 34 | `lattice/social` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 35 | `lattice/testing` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 36 | `lattice/transport-kafka` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 37 | `lattice/transport-nats` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 38 | `lattice/transport-rabbitmq` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 39 | `lattice/transport-sqs` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 40 | `lattice/validation` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 41 | `lattice/workflow-store` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 42 | `lattice/workflow` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | None |

### All 4 Starters

| # | Starter | Type | Description | Autoload | Require-dev | Autoload-dev | Scripts | Keywords | Issues |
|---|---------|------|-------------|----------|-------------|-------------|---------|----------|--------|
| 1 | `lattice/starter-api` | project | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 2 | `lattice/starter-grpc` | project | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 3 | `lattice/starter-service` | project | Yes | Yes | Yes | Yes | Yes | Yes | None |
| 4 | `lattice/starter-workflow` | project | Yes | Yes | Yes | Yes | Yes | Yes | None |

---

## Validation Checklist

- [x] All 42 packages have `name` following `lattice/<package>` convention
- [x] All 42 packages have `description`
- [x] All 42 packages have `license: MIT`
- [x] All 42 packages have `type: library`
- [x] All 42 packages have `keywords`
- [x] All 42 packages have `homepage`
- [x] All 42 packages have `support` (issues + source)
- [x] All 42 packages have `require` with `php: ^8.4`
- [x] All 42 packages have `require-dev` with phpunit
- [x] All 42 packages have `autoload` with correct PSR-4 namespace mapping
- [x] All 42 packages have `autoload-dev` with correct PSR-4 test namespace mapping
- [x] All `src/` and `tests/` directories exist
- [x] No `repositories` with `type: path` remain
- [x] No `minimum-stability` or `prefer-stable` remain
- [x] All cross-package dependencies use `^1.0` (not dev-main, @dev, *, or ^0.1)
- [x] All 4 starters have `name` following `lattice/starter-<type>` convention
- [x] All 4 starters have `type: project`
- [x] All 4 starters have `require` versions pointing to `^1.0`
- [x] All 4 starters have `require-dev`, `autoload-dev`, and `scripts`

## Cross-Package Dependency Map

| Package | Depends On (lattice/*) |
|---------|----------------------|
| `lattice/contracts` | (none) |
| `lattice/core` | contracts |
| `lattice/compiler` | contracts |
| `lattice/module` | contracts, compiler |
| `lattice/pipeline` | contracts |
| `lattice/http` | contracts, routing, database, validation |
| `lattice/auth` | contracts |
| `lattice/authorization` | contracts, auth |
| `lattice/jwt` | contracts, auth |
| `lattice/api-key` | contracts |
| `lattice/pat` | contracts (auth in require-dev) |
| `lattice/oauth` | contracts |
| `lattice/social` | contracts |
| `lattice/microservices` | contracts |
| `lattice/mail` | contracts |
| `lattice/notifications` | contracts, mail |
| `lattice/cache` | contracts |
| `lattice/queue` | contracts |
| `lattice/serializer` | contracts |
| `lattice/observability` | contracts |
| `lattice/rate-limit` | contracts |
| `lattice/problem-details` | contracts |
| `lattice/workflow` | contracts |
| `lattice/workflow-store` | contracts, workflow |
| `lattice/testing` | contracts |
| `lattice/roadrunner` | contracts |
| `lattice/transport-kafka` | contracts |
| `lattice/transport-nats` | contracts |
| `lattice/transport-rabbitmq` | contracts |
| `lattice/transport-sqs` | contracts |
| `lattice/validation` | contracts |
| `lattice/database` | contracts |
| `lattice/routing` | (none) |
| `lattice/events` | (none, uses illuminate) |
| `lattice/filesystem` | (none, uses illuminate) |
| `lattice/http-client` | (none) |
| `lattice/jsonapi` | (none) |
| `lattice/openapi` | (none) |
| `lattice/openswoole` | (none) |
| `lattice/scheduler` | (none) |
| `lattice/devtools` | (none, uses illuminate) |
