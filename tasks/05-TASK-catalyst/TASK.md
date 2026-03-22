# 05 — Catalyst: AI Development Accelerator

> Make LatticePHP AI-agent-friendly with auto-generated guidelines, agent skills, and an MCP dev tools server

## Dependencies
- `lattice/core` (service container, configuration)
- `lattice/module` (module system integration)
- `lattice/database` (database schema/query MCP tools)
- `lattice/routing` (route list MCP tool)
- `lattice/compiler` (compile-time discovery of guidelines/skills)

## Subtasks

### 1. [ ] Guidelines system — file format, auto-detect installed packages, generate per-package guidelines

#### Guidelines File Format
- Define guidelines file format: Markdown (`.md`) with optional Blade-style templating (`.blade.php`)
- Create `GuidelineFile` value object with `path`, `package`, `version`, `content` properties
- Create `GuidelineParser` that reads a guideline file and resolves template variables
- Support version-aware guidelines: load the correct guideline variant based on installed package version
- Unit tests for parsing guidelines with and without template variables

#### Auto-Detection of Installed Packages
- Create `PackageDetector` class that reads `composer.json` and `composer.lock`
- Extract all installed `lattice/*` packages with their resolved versions
- Provide `getInstalledPackages(): array` returning package name => version pairs
- Handle edge cases: dev dependencies, replaced packages, aliased versions
- Unit tests for detection with various `composer.lock` fixtures

#### Guideline Generation Pipeline
- Create `GuidelineGenerator` class that orchestrates detection + file generation
- For each installed lattice package, locate and render its guideline template
- Support custom guidelines directory (`.ai/guidelines/`) with override support
- Custom guidelines take precedence over bundled guidelines for the same package
- Output generated guidelines to a configurable output directory
- Unit tests for generation pipeline including custom overrides

#### CLAUDE.md and AGENTS.md Generation
- Create `ClaudeMdGenerator` that compiles all active guidelines into a single `CLAUDE.md`
- Create `AgentsMdGenerator` that produces a generic `AGENTS.md` for non-Claude agents
- Include project-level context (PHP version, framework version, installed modules) in generated files
- Support appending custom project-specific instructions from `.ai/guidelines/project.md`
- Unit tests for both generators with various package combinations

- **Verify:** `GuidelineGenerator` produces correct per-package guidelines; custom overrides work; `CLAUDE.md` includes all active guidelines

### 2. [ ] Built-in guidelines for all 42 core packages

- Write a guideline Markdown file for each of the 42 core lattice packages:
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
- Each guideline covers: package purpose, key classes/interfaces, common patterns, attribute usage, testing conventions, gotchas
- Store bundled guidelines in `resources/catalyst/guidelines/{package-name}.md`
- Verify each guideline renders correctly through the template engine
- **Verify:** All 42 guideline files exist and are parseable; generating guidelines for a project with all packages installed produces a comprehensive `CLAUDE.md`

### 3. [ ] `php lattice catalyst:install` command

- Create `CatalystInstallCommand` registered as `catalyst:install`
- Detect installed lattice packages via `PackageDetector`
- Generate all per-package guidelines to `.ai/guidelines/`
- Generate `CLAUDE.md` at project root
- Generate `AGENTS.md` at project root
- Generate `.mcp.json` for MCP server auto-registration
- Print summary of generated files (count, paths) to console
- Support `--force` flag to overwrite existing files without prompting
- Support `--dry-run` flag to preview what would be generated
- Create `CatalystUpdateCommand` registered as `catalyst:update` for refreshing guidelines when packages change
- Create `CatalystServiceProvider` that registers all commands and configuration
- Unit tests for command execution with mock file system
- **Verify:** Running `php lattice catalyst:install` in a project with lattice packages produces a valid `CLAUDE.md`, `AGENTS.md`, `.mcp.json`, and per-package guideline files

### 4. [ ] Agent skills system — SKILL.md format, skill discovery, bundled skills

#### Skill File Format
- Define `SKILL.md` format: YAML frontmatter (`name`, `description`, `triggers`) + Markdown body
- Create `SkillFile` value object with parsed frontmatter and content
- Create `SkillParser` that reads YAML frontmatter and validates required fields
- Unit tests for parsing valid and invalid skill files

#### Skill Discovery
- Create `SkillDiscovery` class that scans `.ai/skills/*/SKILL.md` for project-level skills
- Scan `resources/catalyst/skills/*/SKILL.md` for bundled skills
- Scan third-party packages for skills at `resources/catalyst/skills/*/SKILL.md`
- Merge discovered skills with project-level skills taking precedence
- Create `SkillRegistry` to hold all discovered skills and provide lookup by name/trigger

#### Bundled Skills
- Create bundled skill files for core development workflows:
  - `workflow-development` — building durable workflows with `lattice/workflow`
  - `module-authoring` — creating `#[Module]` classes and service providers
  - `pipeline-patterns` — using `lattice/pipeline` for middleware and data processing
  - `database-migrations` — schema management and migration best practices
  - `testing-patterns` — testing conventions with `lattice/testing`
  - `attribute-design` — designing and using PHP attributes in the Lattice style
  - `guard-development` — implementing authentication guards
  - `interceptor-patterns` — building interceptors for cross-cutting concerns

#### Skill Listing Command
- Create `CatalystSkillsCommand` registered as `catalyst:skills`
- List all discovered skills in a formatted table (name, description, source)
- Support `--json` flag for machine-readable output
- Unit tests for command output

- **Verify:** `catalyst:skills` lists all bundled and custom skills; skill files parse without errors; third-party skill loading works

### 5. [ ] MCP dev tools server — JSON-RPC, 10 tools (app info, db schema, db query, routes, modules, logs, docs, config)

#### MCP Server Core
- Implement MCP server using JSON-RPC 2.0 protocol over stdio transport
- Create `McpServer` class that reads JSON-RPC requests from stdin and writes responses to stdout
- Implement request parsing, method routing, and error handling per JSON-RPC spec
- Support `initialize`, `tools/list`, and `tools/call` MCP protocol methods
- Handle malformed requests gracefully with proper JSON-RPC error codes
- Unit tests for JSON-RPC request/response cycle

#### Tool: Application Info
- Return PHP version, LatticePHP framework version, list of installed packages with versions, registered modules
- Read data from the service container, composer.json, and module registry
- Unit test for response structure

#### Tool: Database Schema
- List all database tables with row counts
- Describe a specific table: columns (name, type, nullable, default), indexes, foreign keys
- Read schema from `lattice/database` schema inspector
- Unit tests for table listing and description

#### Tool: Database Query
- Execute read-only `SELECT` queries against the application database
- Enforce read-only mode: reject INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE statements
- Add configurable query timeout (default: 5 seconds)
- Return results as JSON array of objects
- Unit tests for query execution and SQL validation

#### Tool: Route List
- Return all registered routes with method, path, controller/handler, middleware stack, route name
- Read from `lattice/routing` route registry
- Unit test for route list structure

#### Tool: Module Graph
- Return the module dependency tree showing all registered modules and their dependencies
- Read from `lattice/module` module registry
- Unit test for graph structure

#### Tool: Last Error
- Read the latest exception/error from application logs
- Return exception class, message, file, line, and stack trace
- Unit test with mock log data

#### Tool: Read Log Entries
- Return the last N log entries with optional level filter (error, warning, info, debug)
- Support configurable entry count (default: 50)
- Unit test for log reading and filtering

#### Tool: Search Docs
- Query LatticePHP documentation with keyword/semantic search
- Index bundled documentation or search against file content
- Return matching doc sections with relevance ranking
- Unit test for search functionality

#### Tool: Config Reader
- Read framework configuration values by key (e.g., `database.default`, `cache.driver`)
- Support nested key traversal with dot notation
- Redact sensitive values (passwords, secrets, API keys) by default
- Unit test for config reading and redaction

- **Verify:** MCP server starts, responds to `tools/list` with all 10 tools, and each `tools/call` returns correct data

### 6. [ ] `php lattice catalyst:mcp` command

- Create `CatalystMcpCommand` registered as `catalyst:mcp`
- Start the MCP server process with stdio transport
- Boot the LatticePHP application container so tools can access services
- Print startup banner with server version and available tools to stderr (stdout reserved for JSON-RPC)
- Handle SIGINT/SIGTERM for graceful shutdown
- Support `--verbose` flag for debug logging of all JSON-RPC messages to stderr
- Generate `.mcp.json` configuration file for auto-registration with AI agents
  - Include command path, available tools, and metadata
- Unit tests for command initialization and MCP server lifecycle
- **Verify:** `php lattice catalyst:mcp` starts, accepts JSON-RPC requests via stdin, and responds with tool results via stdout

### 7. [ ] Agent integrations (Cursor, Claude Code, Codex, Gemini CLI)

#### Cursor Integration
- Generate `.cursor/mcp.json` with Catalyst MCP server configuration
- Inject guidelines into Cursor's project context via `.cursorrules` or equivalent
- Document Cursor setup steps

#### Claude Code Integration
- Generate `CLAUDE.md` with all guidelines and project context (handled by `catalyst:install`)
- Register MCP server in `.mcp.json` for Claude Code auto-discovery
- Document Claude Code setup steps

#### Codex Integration
- Generate `AGENTS.md` with agent-agnostic instructions
- Register MCP server for Codex consumption
- Document Codex setup steps

#### Gemini CLI Integration
- Generate `GEMINI.md` with Gemini-compatible instructions
- Register MCP server for Gemini CLI
- Document Gemini CLI setup steps

#### Agent Detection
- Create `AgentDetector` class that identifies which AI agent is currently running
- Detection heuristics: environment variables, process name, known file patterns
- Auto-configure the appropriate integration files on `catalyst:install`
- Unit tests for agent detection logic

- **Verify:** Running `catalyst:install` detects the active agent and generates the correct configuration files; MCP server is accessible from each supported agent

### 8. [ ] Third-party extensibility + tests + docs

#### Package Developer API
- Document how third-party packages can ship guidelines in `resources/catalyst/guidelines/`
- Document how third-party packages can ship skills in `resources/catalyst/skills/`
- Auto-load third-party guidelines and skills during `catalyst:install` and `catalyst:update`
- Create `CatalystExtensionInterface` for packages that want to register custom MCP tools

#### Custom Agent Registration
- Create API for registering unsupported AI agents with custom config file generators
- Support custom output format (file name, content template) per agent

#### Composer Hook
- Register Composer `post-update-cmd` hook to auto-run `catalyst:update` after dependency changes
- Configurable: opt-in via `extra.catalyst.auto-update` in project `composer.json`
- Unit test for hook registration

#### Tests
- Unit tests for guideline generation pipeline end-to-end
- Unit tests for skill loading and discovery
- Unit tests for all 10 MCP tools with realistic mock data
- Unit tests for agent detection and configuration generation
- Integration test: install Catalyst in a test project, verify all outputs
- Integration test: start MCP server, send tool requests, verify responses

#### Documentation
- Installation guide (Composer require, module registration)
- Guidelines customization guide
- Skills authoring guide for package developers
- MCP server reference (all tools with request/response examples)
- Agent integration guide per supported agent
- Third-party extensibility guide

- **Verify:** Third-party packages can contribute guidelines and skills; all tests pass; documentation covers all features

## Integration Verification
- [ ] `php lattice catalyst:install` generates a working `CLAUDE.md` containing guidelines for all installed packages
- [ ] `php lattice catalyst:mcp` starts and responds to `tools/list` and `tools/call` for all 10 tools
- [ ] MCP `app_info` tool returns correct PHP and framework versions
- [ ] MCP `db_schema` tool returns table list matching the actual database
- [ ] MCP `db_query` tool executes a SELECT and returns results; rejects a DELETE
- [ ] MCP `route_list` tool returns all registered routes
- [ ] `catalyst:skills` lists all bundled skills
- [ ] Third-party guideline from a test package appears in generated CLAUDE.md
- [ ] End-to-end: new project, install Catalyst, verify agent can use MCP tools
