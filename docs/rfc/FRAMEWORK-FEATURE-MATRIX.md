# LatticePHP Framework Feature Matrix — Comprehensive Competitive Analysis

> **Generated:** 2026-03-22
> **Purpose:** Exhaustive feature comparison across 7 major backend frameworks to identify gaps in LatticePHP
> **Frameworks compared:** Laravel 12, NestJS 11, Django 5.2, Rails 8.1, Spring Boot 3.x/4, AdonisJS 6, FastAPI 0.115+

---

## Legend

| Symbol | Meaning |
|--------|---------|
| YES | Framework provides this natively or via official first-party package |
| PLUGIN | Available via well-maintained community plugin/package |
| PARTIAL | Some support but incomplete or requires significant custom code |
| NO | Not available |

**Priority Levels:**
- **CRITICAL** — Framework is incomplete without it. Must ship in v1.
- **HIGH** — Most production apps need it. Must ship in v1 or v1.1.
- **MEDIUM** — Common but not universal. Can be v1.2+.
- **LOW** — Nice to have. Post-v1 backlog.
- **DEFER** — Post v1, community can contribute.

**LatticePHP Status:**
- **PLANNED** — Package exists in packages/ directory
- **BUILT** — Package built with passing unit tests (Phase 0-19)
- **WIRED** — Package integrated into request lifecycle (Phase 20+)
- **MISSING** — No package exists, not in current plan
- **GAP** — Package exists but feature is missing from it

---

## A. Authentication & Authorization

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| A1 | Session-based auth | YES | YES | YES | YES | YES | YES | PARTIAL | CRITICAL | GAP — auth pkg exists but session driver missing (backend-only, so cookie/token sessions for APIs) |
| A2 | Token-based auth (API tokens) | YES (Sanctum) | YES (@nestjs/jwt) | YES (DRF tokens) | YES (has_secure_token) | YES (Spring Security) | YES (API tokens) | YES (OAuth2/JWT) | CRITICAL | BUILT — jwt, pat, api-key packages |
| A3 | JWT access + refresh tokens | YES (Sanctum/Passport) | YES (@nestjs/jwt) | PLUGIN (simplejwt) | PLUGIN | YES | YES | YES | CRITICAL | BUILT — jwt package |
| A4 | OAuth2 server (issue tokens) | YES (Passport) | PLUGIN | PLUGIN (oauth-toolkit) | PLUGIN (doorkeeper) | YES (Spring Auth Server) | NO | PARTIAL | HIGH | BUILT — oauth package |
| A5 | OAuth2 client (consume tokens) | YES (Socialite) | YES (passport strategies) | YES (allauth) | PLUGIN (omniauth) | YES (Spring Security OAuth2 Client) | NO | YES | HIGH | BUILT — social package |
| A6 | Social login (Google, GitHub, etc.) | YES (Socialite) | YES (passport strategies) | YES (allauth) | PLUGIN (omniauth) | YES (Spring Security) | NO | PLUGIN | HIGH | BUILT — social package |
| A7 | Multi-guard / multi-auth | YES (guards) | YES (strategies) | YES (backends) | PARTIAL | YES (multiple auth providers) | YES (guards) | YES (dependencies) | HIGH | BUILT — auth package supports guards |
| A8 | Two-factor auth (2FA/MFA) | YES (Jetstream/Fortify) | PLUGIN | PLUGIN (django-otp) | PLUGIN | YES (Spring Security 7 native) | PLUGIN | PLUGIN | HIGH | MISSING — no 2FA package |
| A9 | Password policies (complexity, breach check) | YES (validation rules) | PLUGIN | YES (validators) | PLUGIN | YES (Spring Security) | PARTIAL | PLUGIN | MEDIUM | GAP — validation exists but no password policy rules |
| A10 | Session management (revoke all) | YES | PARTIAL | YES | YES | YES (Spring Session) | YES | NO | MEDIUM | MISSING — no session management |
| A11 | Token blacklisting/revocation | YES (Sanctum) | PLUGIN | PLUGIN | PLUGIN | YES | PARTIAL | PLUGIN | HIGH | GAP — jwt package needs blacklist support |
| A12 | API rate limiting per user/key | YES (throttle middleware) | YES (@nestjs/throttler) | YES (DRF throttling) | YES (rack-throttle) | YES (Spring Security) | YES (limiter) | PLUGIN | CRITICAL | BUILT — rate-limit package |
| A13 | IP allowlisting/blocklisting | YES (middleware) | YES (middleware) | YES (middleware) | YES (middleware) | YES (Spring Security) | YES (middleware) | YES (middleware) | MEDIUM | GAP — pipeline exists but no IP filter guard |
| A14 | RBAC / Permission system | YES (Spatie permissions) | YES (CASL/custom) | YES (built-in permissions) | PLUGIN (pundit/cancancan) | YES (Spring Security roles) | YES (Bouncer) | PLUGIN | CRITICAL | BUILT — authorization package |
| A15 | Policy-based authorization | YES (policies) | YES (CASL) | YES (permissions) | YES (pundit) | YES (method security) | YES (Bouncer) | YES (dependencies) | CRITICAL | BUILT — authorization package |
| A16 | Scope-based authorization (OAuth scopes) | YES (Passport) | YES | PLUGIN | PLUGIN | YES | NO | YES | MEDIUM | GAP — oauth package, needs scope enforcement |
| A17 | Personal Access Tokens | YES (Sanctum) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | YES | PLUGIN | HIGH | BUILT — pat package |
| A18 | API Key authentication | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | HIGH | BUILT — api-key package |
| A19 | Passkey / WebAuthn support | PLUGIN | PLUGIN | PLUGIN | PLUGIN | YES (Spring Security 7) | NO | PLUGIN | MEDIUM | MISSING — no webauthn support |
| A20 | Magic link authentication | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |

---

## B. Team / Workspace / Organization

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| B1 | Teams model (user belongs to many teams) | YES (Jetstream) | NO | NO | NO | NO | NO | NO | HIGH | MISSING — no teams package |
| B2 | Team roles (owner, admin, member) | YES (Jetstream) | NO | NO | NO | NO | NO | NO | HIGH | MISSING |
| B3 | Team-scoped resources | YES (Jetstream) | NO | NO | NO | NO | NO | NO | HIGH | MISSING |
| B4 | Team switching | YES (Jetstream) | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| B5 | Team invitations | YES (Jetstream) | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| B6 | Organization hierarchy (org -> teams -> members) | PLUGIN | NO | PLUGIN (django-organizations) | NO | NO | NO | NO | MEDIUM | MISSING |
| B7 | Workspace isolation | PLUGIN | NO | PLUGIN | NO | NO | NO | NO | MEDIUM | MISSING |

> **Analysis:** Only Laravel (via Jetstream) provides teams natively. This is a DIFFERENTIATOR OPPORTUNITY for LatticePHP. Most SaaS apps need teams. Recommend creating a `teams` package.

---

## C. Multi-Tenancy

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| C1 | Single DB with tenant_id | PLUGIN (tenancy) | PLUGIN | PLUGIN (django-tenants) | PLUGIN (apartment) | PLUGIN | NO | PLUGIN | HIGH | MISSING — no tenancy package |
| C2 | DB per tenant | PLUGIN (tenancy) | PLUGIN | PLUGIN (django-tenants) | PLUGIN (apartment) | PLUGIN | NO | PLUGIN | MEDIUM | MISSING |
| C3 | Schema per tenant | PLUGIN (tenancy) | PLUGIN | YES (django-tenants) | PLUGIN (apartment) | PLUGIN | NO | PLUGIN | MEDIUM | MISSING |
| C4 | Tenant resolution (domain, subdomain, header, path) | PLUGIN (tenancy) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | NO | PLUGIN | HIGH | MISSING |
| C5 | Tenant-aware queues/jobs | PLUGIN | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| C6 | Tenant-aware cache | PLUGIN | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| C7 | Tenant billing/subscription | PLUGIN (Cashier) | NO | NO | NO | NO | NO | NO | LOW | MISSING |

> **Analysis:** No framework provides multi-tenancy natively — all rely on plugins. This is a MAJOR DIFFERENTIATOR OPPORTUNITY. A first-class `tenancy` package would be unique.

---

## D. Database & ORM

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| D1 | Full ORM (Active Record or Data Mapper) | YES (Eloquent) | YES (TypeORM/Prisma) | YES (Django ORM) | YES (ActiveRecord) | YES (JPA/Hibernate) | YES (Lucid) | PLUGIN (SQLAlchemy) | CRITICAL | BUILT — database package wraps illuminate/database (Eloquent) |
| D2 | Relationships (hasOne, hasMany, belongsTo, many-to-many, polymorphic) | YES | YES | YES | YES | YES | YES | PLUGIN | CRITICAL | BUILT — via Eloquent |
| D3 | Migrations | YES | YES (TypeORM) | YES | YES | YES (Flyway/Liquibase) | YES | PLUGIN (Alembic) | CRITICAL | BUILT — via illuminate/database, needs CLI wiring (T29) |
| D4 | Seeders | YES | PLUGIN | YES (fixtures/loaddata) | YES (seeds) | PLUGIN | YES | PLUGIN | HIGH | GAP — needs seeder support |
| D5 | Model factories (for testing) | YES | PLUGIN | YES (factory_boy) | YES (FactoryBot) | PLUGIN | YES | PLUGIN | HIGH | GAP — needs factory support |
| D6 | Soft deletes | YES | YES (TypeORM) | PLUGIN (django-safedelete) | PLUGIN (paranoia) | PLUGIN | YES | PLUGIN | HIGH | BUILT — via Eloquent SoftDeletes trait |
| D7 | Model observers / lifecycle hooks | YES (observers) | YES (subscribers) | YES (signals) | YES (callbacks) | YES (entity listeners) | YES (hooks) | PLUGIN | HIGH | BUILT — via Eloquent, events package |
| D8 | Model events (creating, created, updating, etc.) | YES | YES | YES (signals) | YES (callbacks) | YES | YES | PLUGIN | HIGH | BUILT — via Eloquent |
| D9 | Database transactions | YES | YES | YES | YES | YES | YES | PLUGIN | CRITICAL | BUILT — via illuminate/database |
| D10 | Query scopes (reusable query constraints) | YES | PARTIAL | YES (managers) | YES (scopes) | YES (specifications) | YES (scopes) | PLUGIN | HIGH | BUILT — via Eloquent |
| D11 | Full-text search | YES (Scout) | PLUGIN | PLUGIN (django-haystack) | PLUGIN (pg_search) | YES (Spring Data) | NO | PLUGIN | HIGH | MISSING — no search/scout package |
| D12 | JSON column support | YES | YES | YES (JSONField) | YES | YES | YES | PLUGIN | MEDIUM | BUILT — via Eloquent |
| D13 | UUID/ULID primary keys | YES | YES | YES | YES | YES | YES | PLUGIN | HIGH | BUILT — via Eloquent traits |
| D14 | Sluggable models | PLUGIN (Spatie) | PLUGIN | PLUGIN (django-autoslug) | PLUGIN (friendly_id) | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING — no slug trait |
| D15 | Versionable / Revisionable models | PLUGIN (Spatie) | PLUGIN | PLUGIN (django-reversion) | PLUGIN (paper_trail) | PLUGIN (Envers) | PLUGIN | PLUGIN | MEDIUM | MISSING — no model versioning |
| D16 | Database notifications (store in DB) | YES | NO | NO | NO | NO | YES | NO | MEDIUM | BUILT — notifications package |
| D17 | Read/write splitting (read replicas) | YES | PARTIAL | YES | YES | YES | NO | PLUGIN | MEDIUM | GAP — database package needs replica config |
| D18 | Connection pooling | PARTIAL | YES | YES | YES (connection_pool) | YES (HikariCP) | YES | YES | MEDIUM | GAP — needs RoadRunner/Swoole connection pool |
| D19 | Query logging / slow query detection | YES (Telescope) | PLUGIN | YES (django-debug-toolbar) | YES (bullet) | YES (Actuator) | PARTIAL | PLUGIN | HIGH | GAP — observability exists but needs query integration |
| D20 | Database health checks | PARTIAL | YES (Terminus) | PARTIAL | PARTIAL | YES (Actuator) | NO | PLUGIN | HIGH | GAP — observability package needs DB health |

---

## E. API Features

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| E1 | API versioning | PARTIAL (manual) | YES (URI/header/media-type) | YES (DRF versioning) | PARTIAL | YES (Spring Boot 4 native) | NO | YES (path-based) | HIGH | GAP — routing exists but no versioning support |
| E2 | Rate limiting (per route, per user, per key) | YES | YES (@nestjs/throttler) | YES (DRF) | YES | YES | YES | PLUGIN | CRITICAL | BUILT — rate-limit package |
| E3 | Request throttling (global) | YES | YES | YES | YES | YES | YES | PLUGIN | HIGH | BUILT — rate-limit package |
| E4 | OpenAPI / Swagger documentation | PLUGIN (Scribe) | YES (@nestjs/swagger) | YES (DRF spectacular) | PLUGIN (rswag) | YES (springdoc) | NO | YES (auto-generated) | CRITICAL | BUILT — openapi package |
| E5 | API resources / transformers | YES (API Resources) | YES (interceptors/serializers) | YES (DRF serializers) | YES (jbuilder/AMS) | YES (projections) | PARTIAL | YES (Pydantic models) | CRITICAL | BUILT — serializer package |
| E6 | Pagination — offset-based | YES | YES | YES | YES | YES | YES | PLUGIN | CRITICAL | GAP — needs runtime wiring (T35) |
| E7 | Pagination — cursor-based | YES | PLUGIN | YES (DRF) | PLUGIN | YES (Spring Data) | PARTIAL | PLUGIN | HIGH | GAP — needs cursor pagination |
| E8 | Pagination — keyset-based | PLUGIN | PLUGIN | PLUGIN | PLUGIN | YES | NO | PLUGIN | MEDIUM | MISSING |
| E9 | Filtering (query parameter based) | PLUGIN (Spatie QB) | PLUGIN | YES (DRF filters) | PLUGIN (ransack) | YES (Spring Data) | PARTIAL | YES (Query params) | HIGH | MISSING — no query builder filter package |
| E10 | Sorting (query parameter based) | PLUGIN (Spatie QB) | PLUGIN | YES (DRF ordering) | PLUGIN (ransack) | YES (Spring Data) | PARTIAL | YES | HIGH | MISSING — no sorting support |
| E11 | Searching (query parameter based) | YES (Scout) | PLUGIN | YES (DRF search) | PLUGIN | YES | NO | PLUGIN | MEDIUM | MISSING |
| E12 | Bulk operations (create/update/delete many) | PARTIAL | PARTIAL | PARTIAL | PARTIAL | YES (Spring Data) | NO | PARTIAL | MEDIUM | MISSING |
| E13 | Conditional requests (ETag, If-Modified-Since) | PLUGIN | PLUGIN | YES (ConditionalGetMiddleware) | YES (stale?) | YES (Spring Web) | NO | PLUGIN | MEDIUM | MISSING |
| E14 | HATEOAS links | PLUGIN | PLUGIN | YES (DRF hyperlinked) | PLUGIN | YES (Spring HATEOAS) | NO | PLUGIN | LOW | MISSING |
| E15 | Content negotiation | YES | YES | YES (DRF) | YES (respond_to) | YES (Spring Web) | PARTIAL | YES | MEDIUM | GAP — http package needs content negotiation |
| E16 | JSON:API specification | PLUGIN | PLUGIN | PLUGIN | PLUGIN (jsonapi-resources) | PLUGIN | NO | PLUGIN | MEDIUM | BUILT — jsonapi package |
| E17 | GraphQL support | PLUGIN (Lighthouse) | YES (@nestjs/graphql) | PLUGIN (graphene) | PLUGIN (graphql-ruby) | PLUGIN (Spring GraphQL) | NO | PLUGIN (Strawberry) | MEDIUM | MISSING — no graphql package |
| E18 | Problem Details (RFC 9457) | PLUGIN | PLUGIN | NO | NO | YES (Spring 6 native) | NO | NO | HIGH | BUILT — problem-details package |
| E19 | Request body validation | YES | YES (class-validator) | YES (DRF serializers) | YES (strong params + validations) | YES (Bean Validation) | YES (VineJS) | YES (Pydantic) | CRITICAL | BUILT — validation package |
| E20 | Response compression (gzip/brotli) | YES (middleware) | YES (compression) | YES (GZipMiddleware) | YES (Rack::Deflater) | YES | YES | YES (GZipMiddleware) | MEDIUM | GAP — pipeline exists, needs compression middleware |

---

## F. Background Processing

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| F1 | Queue workers (async job execution) | YES | YES (Bull/BullMQ) | YES (Celery) | YES (ActiveJob + Solid Queue) | YES (Spring Batch) | YES | YES (Celery/ARQ) | CRITICAL | BUILT — queue package |
| F2 | Job batching | YES | PARTIAL | PLUGIN | PARTIAL | YES (Spring Batch) | NO | PLUGIN | HIGH | GAP — queue exists but needs batching |
| F3 | Job chaining | YES | YES | PLUGIN (Celery chains) | PARTIAL | YES | NO | PLUGIN | HIGH | GAP — queue needs chaining |
| F4 | Rate-limited jobs | YES | YES (Bull) | PLUGIN | PARTIAL | PLUGIN | NO | PLUGIN | MEDIUM | GAP — queue needs rate limiting |
| F5 | Unique jobs (prevent duplicates) | YES | YES (Bull) | PLUGIN | YES (Solid Queue) | PLUGIN | NO | PLUGIN | HIGH | GAP — queue needs uniqueness |
| F6 | Failed job handling (retries, dead letter) | YES | YES (Bull) | YES (Celery) | YES | YES | YES | PLUGIN | CRITICAL | GAP — queue needs retry/DLQ |
| F7 | Job middleware | YES | YES | PLUGIN | PARTIAL | YES | NO | PLUGIN | MEDIUM | GAP — queue needs middleware support |
| F8 | Scheduled tasks (cron-like) | YES | YES (@nestjs/schedule) | YES (django-celery-beat) | YES (whenever) | YES (Spring Scheduler) | PARTIAL | PLUGIN | CRITICAL | BUILT — scheduler package |
| F9 | Long-running processes | PARTIAL | PARTIAL | YES (Celery) | PARTIAL | YES (Spring Batch) | PARTIAL | PARTIAL | MEDIUM | BUILT — workflow package handles this |
| F10 | Job priorities | YES | YES (Bull) | YES (Celery) | YES (Solid Queue) | YES | NO | PLUGIN | HIGH | GAP — queue needs priority support |
| F11 | Delayed / scheduled jobs | YES | YES (Bull) | YES (Celery) | YES | YES | NO | PLUGIN | HIGH | GAP — queue needs delayed dispatch |
| F12 | Job events / lifecycle hooks | YES | YES | YES (Celery signals) | YES (callbacks) | YES (listeners) | NO | PLUGIN | MEDIUM | GAP — queue needs lifecycle hooks |

---

## G. Real-time

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| G1 | WebSocket support | YES (Reverb) | YES (built-in gateway) | PLUGIN (channels) | YES (ActionCable) | YES (Spring WebSocket) | YES (Transmit planned) | YES (built-in) | HIGH | MISSING — no websocket package |
| G2 | Server-Sent Events (SSE) | PARTIAL | PARTIAL | PLUGIN | PARTIAL | YES (SseEmitter) | YES (Transmit) | YES (StreamingResponse) | MEDIUM | MISSING |
| G3 | Event broadcasting | YES (Echo + Reverb) | YES (gateway) | PLUGIN (channels) | YES (ActionCable) | YES (STOMP) | YES | PARTIAL | HIGH | MISSING — no broadcasting package |
| G4 | Private channels | YES | YES | PLUGIN | YES | YES | PARTIAL | NO | MEDIUM | MISSING |
| G5 | Presence channels | YES | YES | PLUGIN | YES | PARTIAL | PARTIAL | NO | LOW | MISSING |
| G6 | Real-time notifications | YES | YES | PLUGIN | YES (Turbo Streams) | YES | PARTIAL | PARTIAL | MEDIUM | MISSING |

> **Analysis:** Real-time is a significant gap. For a backend API framework, at minimum SSE support is needed. WebSocket support is HIGH priority for modern APIs. Consider a `websocket` or `realtime` package.

---

## H. Observability

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| H1 | Structured logging (JSON) | YES | YES (built-in logger) | YES (structlog) | YES (tagged logging) | YES (Logback/Log4j) | YES | YES | CRITICAL | BUILT — observability package |
| H2 | Distributed tracing (OpenTelemetry) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | YES (Micrometer + OTel) | PLUGIN | PLUGIN | HIGH | BUILT — observability package |
| H3 | Metrics (Prometheus/StatsD) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | YES (Micrometer/Actuator) | PLUGIN | PLUGIN | HIGH | BUILT — observability package |
| H4 | Health checks (liveness/readiness) | PARTIAL | YES (Terminus) | PLUGIN | PARTIAL | YES (Actuator) | NO | PLUGIN | CRITICAL | GAP — observability needs health check endpoints |
| H5 | Audit logs (who did what when) | PLUGIN (Spatie activity) | PLUGIN | YES (LogEntry) | PLUGIN (audited) | YES (Actuator /auditevents) | PLUGIN | PLUGIN | HIGH | MISSING — no audit log package |
| H6 | Request logging (full request/response) | YES (Telescope) | YES (interceptors) | YES (middleware) | YES (middleware) | YES (Actuator) | PARTIAL | YES (middleware) | HIGH | GAP — observability needs request logging |
| H7 | Slow query logging | YES (Telescope) | PLUGIN | YES (django-debug-toolbar) | YES (bullet) | YES (Actuator) | PARTIAL | PLUGIN | HIGH | GAP — needs query logging integration |
| H8 | Performance profiling | YES (Telescope/Pulse) | PLUGIN | YES (django-silk) | PLUGIN (rack-mini-profiler) | YES (Actuator) | PLUGIN | PLUGIN | MEDIUM | MISSING — no profiler |
| H9 | Application monitoring dashboard | YES (Pulse) | NO | PLUGIN (django-admin) | PLUGIN | YES (Actuator + Grafana) | NO | PLUGIN | MEDIUM | MISSING — no dashboard (backend-only, expose metrics) |
| H10 | Error tracking / reporting | YES (Telescope) | PLUGIN (Sentry) | PLUGIN (Sentry) | PLUGIN (Sentry) | PLUGIN (Sentry) | PLUGIN | PLUGIN | HIGH | GAP — problem-details exists, needs error reporting hooks |
| H11 | Log correlation (trace IDs) | PLUGIN | YES | PLUGIN | PLUGIN | YES (Spring Cloud Sleuth) | PLUGIN | PLUGIN | HIGH | GAP — observability needs correlation IDs |

---

## I. Security

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| I1 | CSRF protection | YES | YES (csurf) | YES | YES | YES | YES | NO (API-only) | MEDIUM | GAP — not needed for pure API, but should support stateful APIs |
| I2 | XSS prevention (output encoding) | YES | YES (helmet) | YES (auto-escaping) | YES | YES | YES | NO (API-only) | MEDIUM | N/A — backend-only JSON API framework |
| I3 | SQL injection prevention (parameterized queries) | YES (Eloquent) | YES (ORM) | YES (ORM) | YES (ActiveRecord) | YES (JPA) | YES (Lucid) | YES (SQLAlchemy) | CRITICAL | BUILT — via Eloquent parameterized queries |
| I4 | Rate limiting | YES | YES | YES | YES | YES | YES | PLUGIN | CRITICAL | BUILT — rate-limit package |
| I5 | Input sanitization | YES | YES (class-transformer) | YES | YES | YES | YES | YES (Pydantic) | HIGH | GAP — validation exists, needs sanitization |
| I6 | Encryption at rest (data encryption) | YES (Crypt facade) | PLUGIN | YES (encrypted fields) | YES (encrypted attributes) | YES (Jasypt) | NO | PLUGIN | HIGH | MISSING — no encryption utilities package |
| I7 | Hashing (bcrypt, argon2) | YES (Hash facade) | PLUGIN (bcrypt) | YES (built-in) | YES (has_secure_password) | YES (Spring Security) | YES | PLUGIN (passlib) | CRITICAL | GAP — auth package needs hashing utilities |
| I8 | Secret management / rotation | PARTIAL (env) | PARTIAL (ConfigService) | PARTIAL (env) | YES (credentials) | YES (Spring Vault) | PARTIAL | PARTIAL | MEDIUM | MISSING — no secret management |
| I9 | Security headers (HSTS, X-Frame, CSP, etc.) | YES (middleware) | YES (helmet) | YES (middleware) | YES (middleware) | YES (Spring Security) | YES (middleware) | PLUGIN | HIGH | GAP — pipeline exists, needs security headers middleware |
| I10 | CORS configuration | YES (config/cors.php) | YES (built-in) | YES (django-cors-headers) | YES (rack-cors) | YES (Spring Web) | YES | YES (CORSMiddleware) | CRITICAL | GAP — http package needs CORS middleware |
| I11 | Content Security Policy | PLUGIN | YES (helmet) | YES (middleware) | YES (secure_headers) | YES (Spring Security) | PARTIAL | PLUGIN | MEDIUM | MISSING |
| I12 | Request signing / verification | PARTIAL | PLUGIN | PLUGIN | PLUGIN | PLUGIN | NO | PLUGIN | LOW | MISSING |

---

## J. Developer Experience

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| J1 | CLI tool (artisan-like) | YES (artisan) | YES (nest cli) | YES (manage.py) | YES (rails) | YES (spring init) | YES (ace) | NO | CRITICAL | BUILT — devtools package, needs wiring (T22) |
| J2 | Code generators (model, controller, migration) | YES (make:*) | YES (generate) | YES (startapp) | YES (generate) | YES (Spring Initializr) | YES (make:*) | NO | CRITICAL | GAP — devtools exists, needs generators |
| J3 | Hot reload in development | PARTIAL (Octane --watch) | YES (--watch) | YES (runserver) | YES (auto-reload) | YES (spring-devtools) | YES (--watch) | YES (--reload) | HIGH | GAP — roadrunner needs watch mode |
| J4 | Database GUI / admin panel | YES (Telescope/Pulse) | NO | YES (Django Admin!) | PLUGIN | YES (Spring Boot Admin) | NO | NO | MEDIUM | MISSING — backend-only, but could expose API for admin |
| J5 | Debug toolbar / inspector | YES (Telescope) | PLUGIN | YES (django-debug-toolbar) | PLUGIN (web-console) | YES (Actuator) | NO | PLUGIN | MEDIUM | MISSING |
| J6 | Error pages with context | YES | YES | YES (debug page) | YES (better_errors) | YES (Whitelabel) | YES | YES (automatic) | HIGH | GAP — problem-details exists, needs dev-mode detail |
| J7 | Testing utilities | YES (PHPUnit + helpers) | YES (Jest + @nestjs/testing) | YES (TestCase + Client) | YES (Minitest/RSpec) | YES (Spring Test) | YES (Japa) | YES (TestClient) | CRITICAL | BUILT — testing package |
| J8 | REPL (tinker) | YES (tinker) | NO | YES (shell) | YES (rails console) | PARTIAL (Spring Shell) | YES (ace repl) | YES (ipython) | MEDIUM | MISSING — no REPL |
| J9 | Database migration status | YES | PARTIAL | YES (showmigrations) | YES (db:migrate:status) | YES (Flyway info) | YES | PLUGIN | HIGH | GAP — needs CLI command |
| J10 | Environment configuration | YES (.env + config/) | YES (@nestjs/config) | YES (settings.py) | YES (credentials + env) | YES (application.yml) | YES (env) | YES (.env) | CRITICAL | GAP — core needs config system (T26) |
| J11 | Configuration caching | YES (config:cache) | PARTIAL | NO | NO | YES (auto) | NO | NO | MEDIUM | GAP — compiler could cache config |
| J12 | Route listing | YES (route:list) | YES (Explorer) | YES (show_urls) | YES (routes) | YES (mappings actuator) | YES (list:routes) | YES (openapi) | HIGH | GAP — needs CLI command |
| J13 | Maintenance mode | YES | NO | NO | PARTIAL | NO | YES | NO | LOW | MISSING |

---

## K. Infrastructure / Integrations

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| K1 | File storage (local, S3, GCS) | YES (Storage facade) | PLUGIN | YES (default-storage) | YES (ActiveStorage) | YES (Spring Resource) | YES (Drive) | PLUGIN | CRITICAL | BUILT — filesystem package |
| K2 | Mail sending | YES (Mail facade) | PLUGIN (nodemailer) | YES (django.core.mail) | YES (ActionMailer) | YES (Spring Mail) | YES (mail) | PLUGIN | CRITICAL | BUILT — mail package |
| K3 | SMS sending | PLUGIN (Vonage) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |
| K4 | Push notifications | YES (via notifications) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |
| K5 | Notification system (multi-channel) | YES (Notifications) | NO | NO | PLUGIN (noticed) | NO | NO | NO | HIGH | BUILT — notifications package |
| K6 | PDF generation | PLUGIN (DomPDF/Snappy) | PLUGIN | PLUGIN (reportlab) | PLUGIN (wicked_pdf) | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |
| K7 | Image processing | PLUGIN (Intervention) | PLUGIN (sharp) | PLUGIN (Pillow) | PLUGIN (image_processing) | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |
| K8 | CSV/Excel export | PLUGIN (Maatwebsite) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |
| K9 | Webhook sending | PLUGIN (Spatie) | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | MEDIUM | MISSING — no webhook package |
| K10 | Webhook receiving (inbound) | PLUGIN (Spatie) | PLUGIN | PLUGIN | YES (ActionMailbox for email) | PLUGIN | PLUGIN | PLUGIN | MEDIUM | MISSING |
| K11 | Feature flags | YES (Pennant) | PLUGIN | PLUGIN (django-waffle) | PLUGIN (flipper) | PLUGIN | NO | PLUGIN | HIGH | MISSING — no feature flags package |
| K12 | A/B testing | PLUGIN | PLUGIN | PLUGIN | PLUGIN | PLUGIN | NO | PLUGIN | LOW | MISSING |
| K13 | HTTP client | YES (Http facade) | YES (HttpModule/axios) | YES (requests) | YES (Net::HTTP) | YES (RestClient/WebClient) | PARTIAL | YES (httpx) | CRITICAL | BUILT — http-client package |
| K14 | Task scheduling | YES (Schedule) | YES (@nestjs/schedule) | YES (celery-beat) | YES (whenever) | YES (Spring Scheduler) | PARTIAL | PLUGIN | CRITICAL | BUILT — scheduler package |
| K15 | Inbound email processing | YES (Mailgun webhooks) | PLUGIN | PLUGIN | YES (ActionMailbox) | PLUGIN | PLUGIN | PLUGIN | LOW | MISSING |
| K16 | Signed URLs | YES | PLUGIN | YES | YES | YES | NO | PLUGIN | MEDIUM | MISSING |
| K17 | Temporary URLs (expiring) | YES | PLUGIN | PLUGIN | YES (ActiveStorage) | PLUGIN | NO | PLUGIN | MEDIUM | MISSING |

---

## L. Microservices & Messaging

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| L1 | Message transport abstraction | PARTIAL | YES (built-in) | NO | NO | YES (Spring Integration) | NO | NO | CRITICAL | BUILT — microservices package |
| L2 | gRPC support | PLUGIN | YES (built-in) | PLUGIN | PLUGIN | YES (Spring gRPC) | NO | PLUGIN | HIGH | BUILT — grpc package |
| L3 | NATS transport | NO | YES | NO | NO | PLUGIN | NO | NO | MEDIUM | BUILT — transport-nats |
| L4 | RabbitMQ transport | PLUGIN | YES | YES (Celery) | PLUGIN | YES (Spring AMQP) | NO | PLUGIN | HIGH | BUILT — transport-rabbitmq |
| L5 | SQS transport | YES | YES | YES (Celery) | PLUGIN | YES (Spring Cloud AWS) | NO | PLUGIN | HIGH | BUILT — transport-sqs |
| L6 | Kafka transport | PLUGIN | YES | YES (Celery) | PLUGIN | YES (Spring Kafka) | NO | PLUGIN | HIGH | BUILT — transport-kafka |
| L7 | CQRS pattern | PLUGIN | YES (@nestjs/cqrs) | PLUGIN | PLUGIN | PLUGIN (Axon) | NO | PLUGIN | MEDIUM | MISSING — no CQRS package |
| L8 | Event sourcing | PLUGIN (Spatie) | PLUGIN | PLUGIN | PLUGIN | PLUGIN (Axon) | NO | PLUGIN | MEDIUM | PARTIAL — workflow-store has event history |
| L9 | Saga / distributed transactions | PLUGIN | PLUGIN | NO | NO | PLUGIN (Axon) | NO | NO | HIGH | BUILT — workflow package (compensation/saga) |
| L10 | Service discovery | NO | PLUGIN | NO | NO | YES (Spring Cloud Eureka) | NO | NO | MEDIUM | MISSING |
| L11 | Circuit breaker | PLUGIN | PLUGIN | PLUGIN | PLUGIN | YES (Spring Cloud Circuit Breaker) | NO | PLUGIN | HIGH | MISSING — no circuit breaker |
| L12 | API gateway pattern | NO | YES (built-in) | NO | NO | YES (Spring Cloud Gateway) | NO | NO | MEDIUM | MISSING |
| L13 | Request/response pattern (RPC over queue) | PARTIAL | YES | NO | NO | YES | NO | NO | HIGH | GAP — microservices package needs RPC pattern |

---

## M. Workflow & Durable Execution

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| M1 | Durable workflow execution | NO | NO | NO | NO | NO (needs Temporal) | NO | NO | CRITICAL | BUILT — workflow package (UNIQUE DIFFERENTIATOR) |
| M2 | Workflow event sourcing | NO | NO | NO | NO | NO | NO | NO | CRITICAL | BUILT — workflow-store package |
| M3 | Deterministic replay | NO | NO | NO | NO | NO | NO | NO | CRITICAL | BUILT — workflow package |
| M4 | Workflow signals & queries | NO | NO | NO | NO | NO | NO | NO | HIGH | BUILT — workflow package |
| M5 | Compensation / saga patterns | PLUGIN | PLUGIN | NO | NO | PLUGIN | NO | NO | HIGH | BUILT — workflow package |
| M6 | Timer-based workflows | NO | NO | NO | NO | NO | NO | NO | HIGH | BUILT — workflow package |

> **Analysis:** This is LatticePHP's KILLER FEATURE. No other framework provides native durable execution. Temporal requires external infrastructure; LatticePHP does it with DB + queue.

---

## N. Compliance & Enterprise

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| N1 | GDPR data export (user data dump) | PLUGIN | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| N2 | GDPR data deletion (right to be forgotten) | PLUGIN | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| N3 | Data retention policies | NO | NO | NO | NO | NO | NO | NO | MEDIUM | MISSING |
| N4 | Audit trail (who/what/when/where) | PLUGIN (Spatie activity) | PLUGIN | YES (LogEntry via admin) | PLUGIN (audited) | YES (Actuator) | PLUGIN | PLUGIN | HIGH | MISSING — no audit package |
| N5 | Consent management | NO | NO | NO | NO | NO | NO | NO | LOW | MISSING |
| N6 | Data anonymization | PLUGIN | NO | NO | NO | NO | NO | NO | LOW | MISSING |
| N7 | SOC 2 logging | PARTIAL | PARTIAL | PARTIAL | PARTIAL | YES (Actuator) | NO | PARTIAL | MEDIUM | GAP — observability needs structured audit events |

---

## O. Runtime & Deployment

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| O1 | PHP-FPM support | YES | N/A | N/A | N/A | N/A | N/A | N/A | CRITICAL | PLANNED — baseline runtime |
| O2 | Long-running process support (RoadRunner) | YES (Octane) | N/A | N/A | N/A | N/A | N/A | N/A | CRITICAL | BUILT — roadrunner package |
| O3 | Coroutine support (Swoole) | YES (Octane) | N/A | N/A | N/A | N/A | N/A | N/A | MEDIUM | BUILT — openswoole package (experimental) |
| O4 | Graceful shutdown | YES | YES | YES | YES (Puma) | YES | YES | YES (uvicorn) | HIGH | GAP — roadrunner needs graceful shutdown |
| O5 | Zero-downtime deploys | PLUGIN | YES | PLUGIN | YES (Kamal 2) | YES | PLUGIN | PLUGIN | MEDIUM | MISSING — deployment is app-level concern |
| O6 | Docker support | YES (Sail) | YES | YES | YES (Kamal) | YES (Spring Boot Docker) | YES | YES | HIGH | MISSING — no Docker templates |
| O7 | Kubernetes readiness/liveness | PARTIAL | YES (Terminus) | PLUGIN | PARTIAL | YES (Actuator) | NO | PLUGIN | HIGH | GAP — observability needs K8s probes |

---

## P. Serialization & Data Transformation

| # | Feature | Laravel | NestJS | Django | Rails | Spring | AdonisJS | FastAPI | Priority | LatticePHP Status |
|---|---------|---------|--------|--------|-------|--------|----------|---------|----------|-------------------|
| P1 | JSON serialization | YES | YES | YES | YES | YES (Jackson) | YES | YES (Pydantic) | CRITICAL | BUILT — serializer package |
| P2 | DTO / Data Transfer Objects | PLUGIN (Spatie DTO) | YES (class-validator) | YES (serializers) | PARTIAL | YES (records) | YES | YES (Pydantic models) | CRITICAL | BUILT — validation package handles DTOs |
| P3 | Request/Response transformation | YES (Resources) | YES (interceptors) | YES (serializers) | YES (jbuilder) | YES (projections) | PARTIAL | YES (response_model) | HIGH | BUILT — serializer package |
| P4 | Sparse fieldsets | PLUGIN | PLUGIN | YES (DRF fields param) | PLUGIN | YES (projections) | NO | PARTIAL | MEDIUM | GAP — jsonapi supports, needs general support |
| P5 | Nested resource serialization | YES | YES | YES | YES | YES | YES | YES | HIGH | BUILT — serializer package |

---

## PRIORITY SUMMARY: Missing Features by Priority

### CRITICAL (Must have for v1 — framework is broken without these)

These are already mostly covered by existing packages, but the integration work (Phase 20+) is what's truly critical. No new packages needed for CRITICAL items.

### HIGH Priority — New Packages/Features Needed

| # | Feature | Recommendation |
|---|---------|---------------|
| A8 | Two-factor auth (2FA/MFA) | Add to `auth` package or create `mfa` package |
| A11 | Token blacklisting | Add to `jwt` package |
| A19 | Passkey / WebAuthn | DEFER to post-v1 |
| B1-B5 | Teams/Workspaces | Create `teams` package — DIFFERENTIATOR |
| C1-C4 | Multi-tenancy | Create `tenancy` package — DIFFERENTIATOR |
| D4-D5 | Seeders + Factories | Add to `database` package |
| D11 | Full-text search | Create `search` package (Scout-like) |
| E1 | API versioning | Add to `routing` package |
| E7 | Cursor pagination | Add to `database` or `http` package |
| E9-E10 | Filtering/Sorting | Create `query-filter` package (Spatie QB-like) |
| F2-F7 | Advanced job features | Enhance `queue` package (batching, chaining, uniqueness, DLQ) |
| G1-G3 | WebSocket/SSE/Broadcasting | Create `realtime` package |
| H4 | Health checks | Add to `observability` package |
| H5 | Audit logs | Create `audit` package |
| I6 | Encryption utilities | Create `encryption` package or add to `core` |
| I7 | Hashing utilities | Add to `auth` package |
| I9-I10 | Security headers + CORS | Add middleware to `http` package |
| J2 | Code generators | Enhance `devtools` package |
| K5 | Notification channels | Enhance `notifications` package |
| K9-K10 | Webhooks | Create `webhook` package |
| K11 | Feature flags | Create `feature-flags` package (Pennant-like) |
| L7 | CQRS | Create `cqrs` package |
| L11 | Circuit breaker | Create `circuit-breaker` package |
| N4 | Audit trail | Create `audit` package |

### MEDIUM Priority — Nice to have for v1.x

| # | Feature | Recommendation |
|---|---------|---------------|
| A9 | Password policies | Add rules to `validation` package |
| A10 | Session management | Add to `auth` package |
| A13 | IP filtering | Add guard to `pipeline` package |
| A16 | OAuth scope enforcement | Add to `authorization` package |
| D15 | Model versioning | DEFER — community package |
| D17 | Read replicas | Add to `database` package config |
| E8 | Keyset pagination | Add to `database` package |
| E12 | Bulk operations | Add to `database` package |
| E13 | Conditional requests (ETag) | Add middleware to `http` package |
| E15 | Content negotiation | Add to `http` package |
| E17 | GraphQL | DEFER — create `graphql` package post-v1 |
| E20 | Response compression | Add middleware to `http` package |
| I8 | Secret management | DEFER to post-v1 |
| J4 | Admin API | DEFER to post-v1 |
| J8 | REPL | Create `tinker` package post-v1 |
| K9 | Webhooks | Create `webhook` package |
| K16-K17 | Signed/Temporary URLs | Add to `routing` or `http` package |
| L10 | Service discovery | DEFER — cloud-native concern |
| N1-N3 | GDPR utilities | DEFER to v1.x |

### LOW / DEFER

| Feature | Status |
|---------|--------|
| SMS sending | Community package |
| Push notifications | Community package |
| PDF generation | Community package |
| Image processing | Community package |
| CSV/Excel export | Community package |
| A/B testing | Community package |
| Magic link auth | Community package |
| Data anonymization | Community package |
| Consent management | Community package |

---

## NEW PACKAGES TO CREATE (Recommended)

Based on this analysis, these new packages should be added to the LatticePHP roadmap:

### Must Create (HIGH priority, major gaps)

1. **`teams`** — Team/workspace model, roles, invitations, team-scoped resources. Only Laravel has this natively. DIFFERENTIATOR.
2. **`tenancy`** — Multi-tenancy support (single DB, DB-per-tenant, schema-per-tenant). Tenant resolution. No framework does this natively. DIFFERENTIATOR.
3. **`search`** — Full-text search abstraction (Scout-like). Driver-based: database, Meilisearch, Algolia, Typesense.
4. **`query-filter`** — Request-based filtering, sorting, searching, sparse fieldsets on Eloquent queries (Spatie Query Builder-like).
5. **`realtime`** — WebSocket gateway + SSE + event broadcasting. Transport-agnostic.
6. **`audit`** — Audit trail logging. Who did what, when, to which resource. GDPR + SOC 2.
7. **`feature-flags`** — Feature flag system (Pennant-like). Per-user, per-team, percentage rollout.
8. **`webhook`** — Outbound webhook sending with retry + signature. Inbound webhook receiving with verification.
9. **`encryption`** — Encryption at rest, hashing utilities (bcrypt/argon2), signed values.
10. **`circuit-breaker`** — Circuit breaker pattern for external service calls.
11. **`cqrs`** — Command/Query separation module (NestJS-inspired).

### Should Create (MEDIUM priority, competitive parity)

12. **`health`** — Health check system with multiple indicators (DB, cache, queue, disk, custom). K8s liveness/readiness.
13. **`cors`** — CORS middleware (could be part of `http` package).
14. **`security-headers`** — Security headers middleware (HSTS, CSP, X-Frame, etc.).

---

## COMPETITIVE POSITIONING SUMMARY

| Framework | Strengths LatticePHP Should Match | Strengths That Are LatticePHP Advantages |
|-----------|----------------------------------|----------------------------------------|
| **Laravel** | Ecosystem breadth (Telescope, Pulse, Pennant, Cashier, Scout, Socialite, Reverb), developer experience, code generators | LatticePHP has module architecture + durable workflows that Laravel lacks |
| **NestJS** | Module architecture, microservices transports, CQRS, GraphQL, WebSocket gateway, OpenAPI decorators | LatticePHP matches NestJS architecture. PHP ecosystem is richer for enterprise. |
| **Django** | Admin panel, ORM maturity, built-in auth + permissions, signals, batteries-included philosophy | LatticePHP has async workers, durable workflows, better microservice support |
| **Rails** | Convention over configuration, ActiveStorage, ActionMailer, ActionCable, Solid Queue/Cache | LatticePHP has better module isolation, transport abstraction, workflow engine |
| **Spring Boot** | Enterprise features (Actuator, Security, Batch, Cloud, HATEOAS), health checks, metrics | LatticePHP trades Java verbosity for PHP simplicity with similar patterns |
| **AdonisJS** | Lucid ORM similarity, Bouncer, batteries-included TypeScript | LatticePHP has PHP ecosystem advantage, Eloquent > Lucid, workflow engine |
| **FastAPI** | Auto-generated OpenAPI, Pydantic validation, async-first, dependency injection | LatticePHP matches with attribute-based OpenAPI + validation, adds module system |

### LatticePHP's Unique Value Proposition (confirmed by analysis):

1. **Native durable execution** — NO other framework has this built-in (Temporal requires separate infrastructure)
2. **NestJS-style modules in PHP** — No PHP framework has proper module architecture
3. **Transport-agnostic** — HTTP/gRPC/queue messages through same controller patterns
4. **Attribute-driven** — PHP 8.4 attributes as the primary API surface (similar to NestJS decorators but PHP-native)
5. **Teams + Tenancy built-in** (if implemented) — Would be unique across ALL frameworks

---

## Sources

- [Laravel 12 Release Notes](https://laravel.com/docs/12.x/releases)
- [Laravel 12 Features Guide](https://medium.com/@jha.ameet/laravel-12-x-updates-top-10-features-for-developers-2025-guide-a947abc2f6e0)
- [Laracon US 2025 Announcements](https://laravel.com/blog/everything-we-announced-at-laracon-us-2025)
- [Laravel Pennant](https://laravel.com/docs/12.x/pennant)
- [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum)
- [NestJS in 2025](https://leapcell.io/blog/nestjs-2025-backend-developers-worth-it)
- [NestJS Official Site](https://nestjs.com/)
- [NestJS Microservices](https://docs.nestjs.com/microservices/basics)
- [NestJS CQRS](https://docs.nestjs.com/recipes/cqrs)
- [NestJS Throttler](https://github.com/nestjs/throttler)
- [Django 5.2 Release Notes](https://docs.djangoproject.com/en/6.0/releases/5.2/)
- [Django Contrib Packages](https://docs.djangoproject.com/en/5.1/ref/contrib/)
- [Django REST Framework](https://www.django-rest-framework.org/api-guide/filtering/)
- [Rails 8.0 Release Notes](https://guides.rubyonrails.org/8_0_release_notes.html)
- [Rails 8 Features](https://rubyroidlabs.com/blog/2025/11/rails-8-8-1-new-features/)
- [Spring Boot 3.x Features](https://www.danvega.dev/blog/spring-boot-3-features)
- [Spring Boot Actuator](https://www.baeldung.com/spring-boot-actuators)
- [Spring Boot Starters](https://www.geeksforgeeks.org/springboot/spring-boot-starters/)
- [AdonisJS 6 Plans](https://adonisjs.com/blog/what-to-expect-of-adonisjs-6)
- [AdonisJS Official Site](https://adonisjs.com/)
- [FastAPI Features](https://fastapi.tiangolo.com/features/)
- [FastAPI Best Practices](https://fastlaunchapi.dev/blog/fastapi-best-practices-production-2026)
