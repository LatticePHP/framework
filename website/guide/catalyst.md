---
outline: deep
---

# Catalyst

Catalyst is LatticePHP's AI development accelerator. It provides MCP tools, guidelines, and skills that help AI coding assistants (Claude Code, Cursor, Copilot) understand and work with your LatticePHP project.

## Installation

```bash
php bin/lattice catalyst:install
```

This generates:
- **CLAUDE.md** -- project context and coding conventions for AI assistants
- **.mcp.json** -- MCP server configuration for Claude Code
- **Guidelines** -- project-specific rules and patterns
- **Skills** -- reusable AI prompts for common tasks

## Built-in MCP Tools

Catalyst registers 8 MCP tools that give AI assistants deep insight into your application:

| Tool | Description |
|---|---|
| `ApplicationInfo` | App name, environment, version, loaded modules |
| `ConfigReader` | Read configuration values |
| `DatabaseQuery` | Execute read-only SQL queries |
| `DatabaseSchema` | Inspect table schemas and relationships |
| `LastError` | Get the most recent error/exception |
| `LogEntries` | Read recent log entries with filtering |
| `ModuleGraph` | Visualize module dependency graph |
| `RouteList` | List all registered routes with guards |

## Starting the MCP Server

```bash
# Start MCP dev tools server (for Claude Code)
php bin/lattice catalyst:mcp
```

Then configure your AI tool to connect. For Claude Code, add to `.mcp.json`:

```json
{
    "mcpServers": {
        "lattice": {
            "command": "php",
            "args": ["bin/lattice", "catalyst:mcp"]
        }
    }
}
```

## Skills

Skills are reusable AI prompts for common development tasks. List available skills:

```bash
php bin/lattice catalyst:skills
```

## Guidelines

Guidelines are project-specific rules that help AI assistants write code that matches your conventions. They're generated from your project structure and coding standards.

## Updating

After making changes to your project (new modules, config changes):

```bash
php bin/lattice catalyst:update
```

This refreshes the guidelines and project metadata.

## Next Steps

- [MCP Server](mcp.md) -- expose your own tools for AI assistants
- [CLI Commands](cli.md) -- all catalyst commands
- [Package Authoring](package-authoring.md) -- creating LatticePHP packages
