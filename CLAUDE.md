# CLAUDE.md — LatticePHP Framework

## Project Overview

LatticePHP is a **backend-only PHP 8.4+ framework** — Laravel's Illuminate components as the engine, NestJS-style modular architecture on top, native durable workflow orchestration in the middle.

- **Monorepo:** 42 packages in `packages/`, split to individual repos under `github.com/LatticePHP/*`
- **Language:** PHP 8.4+ (strict_types everywhere, attributes as the public API)
- **License:** MIT
- **Current version:** Check with `git describe --tags --abbrev=0`

## Quick Commands

```bash
make install          # Install Composer deps
make test             # Run full test suite (3,248+ tests)
make test-suite S=Workflow   # Run one test suite
make test-filter F=ClassName # Filter by class/method
make lint             # CS Fixer (dry-run) + PHPStan
make cs-fix           # Auto-fix code style
make stan             # PHPStan level max
make serve            # Local PHP server at :8000
make up               # Docker dev server (SQLite)
make up-full          # Docker full stack (Postgres + Redis + workers)
```

## Architecture

```
packages/             — 42 framework packages (the product)
examples/crm/         — Full CRM reference app (40 routes, 76 E2E tests)
starters/             — 4 starter templates (api, grpc, service, workflow)
docs/adr/             — 14 Architecture Decision Records
docs/guides/          — 17 documentation guides
docker/php/           — PHP config (xdebug, opcache, dev/prod ini)
```

### Package Dependency Direction

```
contracts → core → module → compiler → pipeline → http → routing
                                                       → validation
                                                       → database
                                                       → auth → jwt
                                                               → authorization
                                                       → workflow → workflow-store
```

This is the core dependency chain (simplified). Other packages (`events`, `cache`, `queue`, `mail`, `notifications`, `observability`, `microservices`, transports, etc.) follow the same direction — always depending downward toward `contracts`.

**Rule:** Packages depend on `contracts` for interfaces. Never create circular dependencies between packages. Use `contracts` to break cycles.

### CRUD Base Classes

The framework provides `CrudService` and `CrudController` for mechanical CRUD:
- `Lattice\Database\Crud\CrudService` — transaction-wrapped create/update/delete with lifecycle hooks
- `Lattice\Http\Crud\CrudController` — generic index/show/destroy endpoints

Services with no custom business logic extend CrudService and define only `model()`.
Controllers extend CrudController and keep only custom endpoints (search, stage transitions, etc.).

## Coding Standards

### Non-Negotiable Rules

1. **`declare(strict_types=1);`** in every PHP file — no exceptions
2. **Every class is `final`** unless it's explicitly designed for extension
3. **Every method declares its return type** — no implicit `mixed`
4. **Attributes are the public API** — `#[Module]`, `#[Controller]`, `#[Get]`, `#[UseGuards]`, `#[Body]`, `#[Workflow]`, `#[SignalMethod]`
5. **Use Illuminate directly** — don't wrap Eloquent, Queue, Cache unnecessarily. Add value on top.
6. **No `@author` tags, no `@package` tags** — git blame is authoritative
7. **PHPDoc only when the type system can't express it** (array shapes, generics) — never repeat the native signature

### Naming Conventions

| Thing | Convention | Example |
|-------|-----------|---------|
| Namespace | `Lattice\PackageName\` | `Lattice\Workflow\` |
| Class | PascalCase, `final` | `final class WorkflowRuntime` |
| Interface | `*Interface` suffix | `WorkflowInterface` |
| Attribute | PascalCase | `#[SignalMethod]` |
| Method | camelCase | `executeActivity()` |
| Config keys | dot notation | `jwt.access_token_ttl` |
| Env vars | `LATTICE_` prefix | `LATTICE_JWT_SECRET` |
| CLI commands | `kebab-case` | `php lattice make:module` |

### Code Style

Enforced by `.php-cs-fixer.dist.php`: PER-CS, PHP 8.4 migration, strict params, ordered imports, single quotes, trailing commas.

```bash
make cs-fix    # Auto-fix
make cs-check  # Dry-run check
```

### Static Analysis

PHPStan at level max. Config in `phpstan.neon` with ignore rules for Eloquent dynamic properties and container resolution.

```bash
make stan              # Run analysis
make stan-baseline     # Generate baseline for existing errors
```

## Testing

- **Framework:** PHPUnit 11
- **Test location:** `packages/*/tests/` mirroring `packages/*/src/`
- **Naming:** `test_snake_case_description()` — describes the behavior, not the method
- **Fakes over mocks:** Use the framework's built-in fakes (`InMemoryEventStore`, `FakeMailer`, etc.), not `createMock()`
- **One behavior per test** — focused assertions, specific values

```bash
make test                        # All tests
make test-suite S=Workflow       # One suite
make test-filter F=test_replay   # One test
make test-coverage               # HTML coverage report
```

## Git Conventions

### Branching

```
main                    — stable, always releasable
feat/module-name        — new features
fix/short-description   — bug fixes
docs/topic              — documentation
refactor/area           — refactoring
```

- Branch from `main` for new work
- Never push directly to `main`
- Delete branch after merge

### Commit Messages

Conventional Commits format:

```
<type>(<scope>): <description>

[optional body]
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `ci`, `perf`
Scope: package name or area — `workflow`, `http`, `auth`, `ci`, `docker`

Examples:
```
feat(workflow): add child workflow support
fix(jwt): prevent token reuse after refresh rotation
docs(guides): add migration-from-laravel guide
ci: add Composer dependency caching to test workflow
chore: update .editorconfig and phpstan.neon
```

## Versioning & Release Process

LatticePHP follows [Semantic Versioning](https://semver.org/):

| Version Bump | When | Example |
|-------------|------|---------|
| **Patch** (`1.0.x`) | Bug fixes, typos, minor internal changes. No new features, no breaking changes. | `fix(jwt): handle expired refresh token edge case` |
| **Minor** (`1.x.0`) | New features, new packages, new attributes, new CLI commands — all backwards-compatible. Existing code continues to work unchanged. | `feat(search): add full-text search with Scout driver` |
| **Major** (`x.0.0`) | Breaking changes — renamed classes/methods, removed features, changed attribute signatures, PHP version bump, changed default behavior. | Removing a public method, changing `#[Module]` attribute parameters |

### How to Release

**Step 1: Update CHANGELOG.md**

Move items from `[Unreleased]` to a new version section:

```markdown
## [Unreleased]

## [1.1.0] - 2026-04-15

### Added
- Full-text search with `#[Searchable]` attribute
- Circuit breaker with configurable thresholds

### Fixed
- JWT refresh token rotation race condition
```

Add the comparison link at the bottom:
```markdown
[Unreleased]: https://github.com/LatticePHP/framework/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/LatticePHP/framework/compare/v1.0.0...v1.1.0
```

**Step 2: Commit the changelog**

```bash
git add CHANGELOG.md
git commit -m "chore: prepare v1.1.0 release"
git push origin main
```

**Step 3: Release**

```bash
make release-patch   # 1.0.0 → 1.0.1  (bug fixes only)
make release-minor   # 1.0.0 → 1.1.0  (new features)
make release-major   # 1.0.0 → 2.0.0  (breaking changes)
make release V=1.2.3 # specific version
```

This will:
1. Verify working tree is clean
2. Verify CHANGELOG.md has the version entry
3. Run the full test suite
4. Create an annotated git tag
5. Push the tag to GitHub

**Step 4: Automated (GitHub Actions handles the rest)**

On tag push, three workflows run:
1. **Tests** — full CI against the tagged commit
2. **Release** — creates a GitHub Release with changelog notes
3. **Monorepo Split** — pushes code + tag to all 42 package repos

### What Goes in Each Version

**Every commit to `main`** should have a corresponding entry in `[Unreleased]` in CHANGELOG.md. Categories:

- **Added** — new features, new packages, new commands
- **Changed** — modifications to existing features (non-breaking)
- **Deprecated** — features that will be removed in a future major version
- **Removed** — features removed (major version only)
- **Fixed** — bug fixes
- **Security** — vulnerability fixes

### Version Decision Guide

Ask yourself:
1. **Does any existing test break?** → Major
2. **Does any public API signature change?** → Major
3. **Is it a new feature or enhancement?** → Minor
4. **Is it a bug fix or internal improvement?** → Patch

When in doubt, go with the lower bump. Multiple patches can be collected into a minor. Multiple minors into a major.

## CI/CD

### Workflows (`.github/workflows/`)

| Workflow | Trigger | What It Does |
|----------|---------|-------------|
| `tests.yml` | push/PR to main | PHPUnit + PHPStan + CS Fixer |
| `release.yml` | tag push `v*` | Creates GitHub Release from CHANGELOG |
| `split.yml` | push to main + tags | Splits monorepo to 42 individual repos |
| `close-pull-request.yml` | PR on split repos | Auto-closes with redirect to monorepo |

### Required for merge: all three jobs in `tests.yml` must pass (tests, PHPStan, CS Fixer).

## Docker

```bash
make up          # SQLite dev server at :8000
make up-full     # Postgres + Redis + queue worker + scheduler
make down        # Stop everything
make shell       # Shell into app container
```

Dockerfile targets:
- `dev` — Xdebug, full deps, hot reload via volume mount
- `production` — FPM, OPcache+JIT, no dev deps, non-root user
- `cli` — production CLI for migrations, workers, schedulers

## Package Development

### Creating a new package

1. Create `packages/my-package/` with `src/`, `tests/`, `composer.json`
2. Add namespace to root `composer.json` (both autoload and autoload-dev)
3. Add test suite to `phpunit.xml`
4. Add to split matrix in `.github/workflows/split.yml`
5. Create the target repo under `github.com/LatticePHP/my-package`

### Package `composer.json`

Each package has its own `composer.json` for when it's installed standalone via the split repos. Must declare its own dependencies (other `lattice/*` packages + illuminate components).

## Key Architecture Decisions

Full ADRs in `docs/adr/`. The critical ones:

- **ADR-001:** Backend-only — no Blade, no SSR, no frontend
- **ADR-005:** Three-tier runtime — FPM (baseline), RoadRunner (first-class), OpenSwoole (experimental)
- **ADR-008:** JWT with asymmetric keys as default auth
- **ADR-010:** Native durable execution engine — deterministic replay, no external Temporal server
- **ADR-012:** API defaults — JSON, RFC 9457 errors, OpenAPI 3.1, cursor pagination

## Ecosystem Projects (Roadmap)

All planned projects live in `projects/` with `PROJECT.md` and `TASKS.md`. Tracked on GitHub Issues + Milestones.

**Naming convention:** No Laravel name reuse. Names reflect purpose through the lattice metaphor.

**Ecosystem packages** (new products with Web SPA + Rich CLI TUI):

| Project | Folder | Package | What It Is | Phase |
|---------|--------|---------|-----------|-------|
| **Catalyst** | `projects/catalyst` | `lattice/catalyst` | AI development accelerator — guidelines, skills, MCP dev tools | v1.0 |
| **Chronos** | `projects/chronos` | `lattice/chronos` | Workflow execution dashboard (Temporal UI) | v1.0 |
| **Loom** | `projects/loom` | `lattice/loom` | Queue monitoring dashboard (weaves job threads) | v1.1 |
| **Nightwatch** | `projects/nightwatch` | `lattice/nightwatch` | Unified monitoring: debug (dev) + metrics (prod). File-system NDJSON storage. | v1.1 |
| **Ripple** | `projects/ripple` | `lattice/ripple` | WebSocket server (events ripple through the lattice) | v1.1 |
| **Prism** | `projects/prism` | `lattice/prism` | Self-hosted error reporting (decomposes errors into spectrum) | v1.1 |
| **Anvil** | `projects/anvil` | `lattice/anvil` | Server management & deployment CLI (where things are forged) | v1.1 |
| **GraphQL** | `projects/graphql` | `lattice/graphql` | Attribute-based GraphQL (`#[Query]`, `#[Mutation]`) | v2.0 |
| **AI** | `projects/ai-module` | `lattice/ai` | Unified AI SDK — 15+ providers, agents, tools, images, audio | v2.0 |
| **MCP** | `projects/mcp-module` | `lattice/mcp` | MCP server — expose services as tools for AI agents | v2.0 |

**Infrastructure projects** (pre-release and foundational work):

| Project | Folder | What It Is | Phase |
|---------|--------|-----------|-------|
| **Packagist Release** | `projects/packagist-release` | Register all 42 packages + 4 starters on Packagist | Pre-release |
| **Queue Workers** | `projects/queue-workers` | Wire workflow activities to queue jobs (not synchronous) | Pre-release |
| **Integration Tests** | `projects/integration-tests` | Fill E2E test gap from 34% to 80%+ | Pre-release |
| **CLI Tools** | `projects/cli-tools` | Missing commands: route:cache, openapi:generate, module:list, etc. | v1.0 |
| **OAuth & Social** | `projects/oauth-social` | Complete OAuth2 grant flows + social auth providers | v1.1 |

## Task Management System

All work is tracked in `tasks/TRACKER.md`. Every new session MUST:

1. **Read `tasks/TRACKER.md`** at session start to understand current progress
2. **Read `CLAUDE.md`** for conventions and standards
3. **Check `memory/`** for any saved context from previous sessions
4. **Pick next task** by wave order (don't skip ahead unless deps are met)
5. **Read the task's `TASK.md`** for subtasks and verification steps
6. **Build subtask by subtask**, verify each before moving to the next
7. **Update `TASK.md` checkboxes** as work completes
8. **Update `tasks/TRACKER.md`** status after each task status changes
9. **Run full test suite** before marking any task as done
10. **Save learnings to `memory/`** if anything non-obvious was discovered

### Task Structure

```
tasks/
  TRACKER.md                         <- Master progress (READ FIRST)
  01-TASK-packagist-release/
    TASK.md                          <- Subtasks + verification steps
  02-TASK-queue-workers/
    TASK.md
  ...
  15-TASK-mcp-module/
    TASK.md
```

### Build Order (Dependency Waves)

```
Wave 1 (no deps):     01-packagist, 02-queue-workers, 03-integration-tests, 04-cli-tools
Wave 2 (foundation):  05-catalyst, 06-ripple
Wave 3 (dashboards):  07-chronos, 08-loom, 09-nightwatch
Wave 4 (products):    10-oauth-social, 11-prism, 12-anvil
Wave 5 (next-gen):    13-graphql, 14-ai-module, 15-mcp-module
```

### Verification Protocol

Every subtask must be verified before marking complete:
- **PHP code:** `make test` passes, `make stan` passes, `make cs-check` passes
- **API endpoints:** curl/httpie test returns expected response
- **Frontend:** component renders correctly, interactions work
- **CLI commands:** command runs, output matches expected format
- **Integration:** feature works end-to-end with other packages

## Frontend Stack (All Web UIs)

All dashboard SPAs (Chronos, Loom, Nightwatch, Prism) use the same stack:

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Framework** | Next.js | React-based SPA, SSR optional |
| **UI Components** | NextUI | Component library |
| **Styling** | TailwindCSS | Utility-first CSS |
| **Data Fetching** | TanStack Query | Server state, caching, polling |
| **Client State** | Zustand | Lightweight global state |
| **Validation** | Zod | Runtime schema validation |
| **Charts** | Recharts or Chart.js | Metrics visualization |

### Frontend Principles

- **Component-by-component, feature-by-feature** — build the smallest useful unit, verify, then compose
- **SOLID + DRY** — single responsibility components, shared hooks, reusable primitives
- **Each dashboard is a switchable module** — can be installed/removed independently
- **API-first** — backend API is the contract, frontend is a consumer
- **Dark/light theme** — supported from day one via TailwindCSS + NextUI theming
- **Responsive** — mobile-friendly layouts for all dashboards
