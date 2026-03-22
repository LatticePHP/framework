# 01 — Packagist Release

> Register all 42 packages + 4 starters on Packagist

## Dependencies
- None (Wave 1)

## Subtasks

### 1. [ ] Validate all package composer.json files
- Audit all 42 `packages/*/composer.json` — verify `name` follows `lattice/<package>` convention
- Verify every package has `description`, `license`, `keywords`, `homepage`, and `support` fields
- Verify `autoload` and `autoload-dev` PSR-4 mappings are correct and match directory structure
- Verify `require` and `require-dev` dependency versions are valid and consistent across packages
- Verify `minimum-stability` and `prefer-stable` are set where needed
- Audit all 4 `starters/*/composer.json` — verify `name` follows `lattice/starter-<type>` convention
- Verify starters have `type: project` set and `require` versions pointing to `^1.0`
- Run `composer validate` on all 42 packages and all 4 starters
- Fix any validation errors found
- **Verify:** `composer validate` passes in every package and starter directory

### 2. [ ] Register LatticePHP org on Packagist
- Register `lattice` organization on Packagist (or verify it exists)
- Configure organization profile (description, URL, logo)
- Add team members with appropriate permissions
- **Verify:** Org visible at packagist.org/packages/lattice/

### 3. [ ] Submit all 42 packages
- Submit each package URL from the split repos, in dependency order:
  - `lattice/contracts`, `lattice/core`, `lattice/compiler`, `lattice/module`, `lattice/pipeline`
  - `lattice/events`, `lattice/http`, `lattice/routing`, `lattice/database`, `lattice/cache`
  - `lattice/queue`, `lattice/validation`, `lattice/serializer`
  - `lattice/auth`, `lattice/authorization`, `lattice/jwt`, `lattice/oauth`, `lattice/social`, `lattice/api-key`, `lattice/pat`
  - `lattice/http-client`, `lattice/mail`, `lattice/notifications`, `lattice/filesystem`, `lattice/scheduler`
  - `lattice/rate-limit`, `lattice/observability`, `lattice/testing`, `lattice/devtools`
  - `lattice/openapi`, `lattice/jsonapi`, `lattice/problem-details`
  - `lattice/grpc`, `lattice/microservices`
  - `lattice/transport-nats`, `lattice/transport-rabbitmq`, `lattice/transport-sqs`, `lattice/transport-kafka`
  - `lattice/workflow`, `lattice/workflow-store`
  - `lattice/openswoole`, `lattice/roadrunner`
- **Verify:** Each package appears on Packagist with correct metadata

### 4. [ ] Submit 4 starter kits
- Submit `lattice/starter-api`
- Submit `lattice/starter-grpc`
- Submit `lattice/starter-service`
- Submit `lattice/starter-workflow`
- **Verify:** Each starter visible on Packagist with `type: project`

### 5. [ ] Configure auto-update webhooks
- Configure GitHub webhook for the monorepo to notify Packagist on push
- Verify webhook fires correctly on a test push/tag
- Verify Packagist reflects the new version within minutes of tagging
- Document the webhook setup for future maintainers
- **Verify:** Tag a test release and confirm Packagist updates automatically

### 6. [ ] Test composer require for each package
- `composer require lattice/core` in a fresh project
- Test `composer require` for every remaining package (41 packages)
- Verify transitive dependencies resolve correctly (e.g., requiring `lattice/auth` pulls in `lattice/core`)
- Verify version constraints are satisfied across the full dependency graph
- Test at least 10 key packages in depth: core, http, routing, auth, workflow, database, queue, grpc, openapi, cache
- **Verify:** Package installs, autoloader works, no version conflicts

### 7. [ ] Test composer create-project for each starter
- `composer create-project lattice/starter-api myapp`
- `composer create-project lattice/starter-grpc myapp`
- `composer create-project lattice/starter-service myapp`
- `composer create-project lattice/starter-workflow myapp`
- Verify each starter boots and serves a basic request after scaffolding
- Run `php lattice serve` and `php lattice test` in each
- **Verify:** All 4 starters create working projects

### 8. [ ] Add README badges
- Add Packagist version badge to root README
- Add Packagist download count badge to root README
- Add Packagist badges to each package's individual README
- Verify all badge URLs resolve and display correctly
- **Verify:** Badges render on GitHub

## Integration Verification
- [ ] Fresh machine test: install a starter, require additional packages, run tests
- [ ] Verify split workflow pushes trigger Packagist updates
- [ ] End-to-end: clone nothing, `composer create-project lattice/starter-api`, add `lattice/workflow`, run full test suite
