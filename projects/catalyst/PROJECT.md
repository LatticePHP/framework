# Catalyst — AI Development Accelerator for LatticePHP

**Package:** `lattice/catalyst`

## Overview

Catalyst is LatticePHP's AI development accelerator — equivalent to Laravel Boost. It makes the framework AI-agent-friendly out of the box.

## Components

### 1. AI Guidelines

Static `.md` files auto-generated per installed lattice package, loaded into AI agent context. Version-aware. Custom overrides supported.

### 2. Agent Skills

On-demand knowledge modules for specific domains (workflow development, module authoring, pipeline patterns, database migrations, testing).

### 3. MCP Dev Tools

`php lattice catalyst:mcp` exposes development tools to AI agents — app info, database schema/query, error logs, route list, module graph, documentation search.

## Multi-Agent Support

Cursor, Claude Code, Codex, Gemini CLI, GitHub Copilot.

## Commands

- `php lattice catalyst:install` — generates CLAUDE.md, .mcp.json, guidelines, skills
- `php lattice catalyst:update` — refreshes guidelines when packages change
- `php lattice catalyst:mcp` — runs the MCP server for AI agents

## Dependencies

| Package             | Role                                      |
|---------------------|-------------------------------------------|
| `lattice/core`      | Service container, configuration          |
| `lattice/module`    | Module system integration                 |
| `lattice/database`  | Database schema/query MCP tools           |
| `lattice/routing`   | Route list MCP tool                       |
| `lattice/compiler`  | Compile-time discovery of guidelines/skills |
