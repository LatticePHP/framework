# Catalyst — Task Breakdown

## Phase 1 — Guidelines System

- [ ] Guidelines file format (.md with optional .blade.php templating)
- [ ] Auto-detect installed lattice packages from composer.json
- [ ] Generate package-specific guidelines (one per installed package)
- [ ] Version-aware guidelines (load correct version based on installed package version)
- [ ] Custom guidelines directory (.ai/guidelines/) with override support
- [ ] Bundled guidelines for all 42 core packages
- [ ] `php lattice catalyst:install` command — generate all guidelines
- [ ] `php lattice catalyst:update` command — refresh when packages change
- [ ] Auto-generate CLAUDE.md from installed packages + custom guidelines
- [ ] Auto-generate AGENTS.md (generic agent instructions)
- [ ] CatalystServiceProvider

## Phase 2 — Agent Skills

- [ ] Skill file format (YAML frontmatter + Markdown: name, description, triggers)
- [ ] Skill discovery from .ai/skills/*/SKILL.md
- [ ] Bundled skills: workflow-development, module-authoring, pipeline-patterns, database-migrations, testing-patterns, attribute-design, guard-development, interceptor-patterns
- [ ] Custom skill creation support
- [ ] Third-party package skill loading (packages can ship their own skills)
- [ ] Skill listing command: `php lattice catalyst:skills`

## Phase 3 — MCP Dev Tools Server

- [ ] MCP server implementation (JSON-RPC 2.0 via stdio)
- [ ] Tool: Application Info (PHP version, framework version, installed packages, registered modules)
- [ ] Tool: Database Schema (list tables, describe table, list columns/indexes/foreign keys)
- [ ] Tool: Database Query (execute read-only SELECT queries)
- [ ] Tool: Route List (all registered routes with method, path, controller, middleware)
- [ ] Tool: Module Graph (module dependency tree)
- [ ] Tool: Last Error (read latest exception from logs)
- [ ] Tool: Read Log Entries (last N log entries with level filter)
- [ ] Tool: Search Docs (query LatticePHP documentation with semantic search)
- [ ] Tool: Config Reader (read framework configuration values)
- [ ] `php lattice catalyst:mcp` command — start MCP server
- [ ] .mcp.json generation for auto-registration with AI agents

## Phase 4 — Agent Integration

- [ ] Cursor integration (MCP settings, guidelines injection)
- [ ] Claude Code integration (CLAUDE.md generation, MCP registration)
- [ ] Codex integration (AGENTS.md, MCP registration)
- [ ] Gemini CLI integration (GEMINI.md, MCP registration)
- [ ] GitHub Copilot integration (MCP server start command)
- [ ] Agent detection (auto-detect which agent is running, configure accordingly)

## Phase 5 — Third-Party Extensibility

- [ ] Package developer API: ship guidelines in resources/catalyst/guidelines/
- [ ] Package developer API: ship skills in resources/catalyst/skills/
- [ ] Auto-load third-party guidelines/skills on `catalyst:install`
- [ ] Custom agent registration API (for unsupported AI agents)

## Phase 6 — Polish

- [ ] Composer post-update hook (auto-run catalyst:update)
- [ ] Documentation
- [ ] Tests for guideline generation, skill loading, MCP tools
- [ ] Example: custom guideline for a project's domain logic
