# MCP Module -- Model Context Protocol Server

**Package:** `lattice/mcp`

## Overview

A Model Context Protocol (MCP) server package for LatticePHP that allows applications to expose tools, resources, and prompts to AI agents via the MCP standard. Developers annotate existing service methods with `#[Tool]`, `#[Resource]`, and `#[Prompt]` attributes to expose them over MCP. The server handles JSON-RPC 2.0 message processing, capability negotiation, and transport management.

The package integrates with the LatticePHP module system so that any module can contribute MCP capabilities. Tools, resources, and prompts are discovered automatically at compile time from all registered modules.

## What is MCP?

The Model Context Protocol is an open standard for connecting AI models to external data sources and tools. An MCP server advertises its capabilities (tools, resources, prompts) and handles invocation requests from MCP clients such as Claude Desktop, IDE extensions, or custom agents.

## Design Philosophy

- **Attributes for declaration**: Use `#[Tool]`, `#[Resource]`, `#[Prompt]` on methods to expose them via MCP. No separate configuration files.
- **Module integration**: Each LatticePHP module can contribute tools, resources, and prompts. The MCP server aggregates them from all registered modules.
- **Transport flexibility**: Support both stdio (for local CLI-based clients) and SSE (for remote/web-based clients).
- **Security**: Authentication and authorization are enforced on every invocation.

## Supported Transports

| Transport | Use Case | Protocol |
|---|---|---|
| **stdio** | Local CLI-based MCP clients (Claude Desktop, IDE extensions) | JSON-RPC 2.0 over stdin/stdout |
| **SSE** | Remote/web-based MCP clients | JSON-RPC 2.0 over HTTP Server-Sent Events |

## Dependencies

| Package | Role |
|---|---|
| `lattice/core` | Service container, configuration |
| `lattice/module` | Module system integration for capability aggregation |
| `lattice/http` | SSE transport HTTP handling |
| `lattice/serializer` | JSON-RPC message serialization/deserialization |
| `lattice/compiler` | Attribute discovery and compilation |
| `lattice/events` | (optional, for tool invocation events) |
| `lattice/cache` | (optional, for tool result caching) |
| `lattice/rate-limit` | (optional, for rate limiting MCP calls) |

## Success Criteria

1. MCP server responds correctly to `initialize`, `tools/list`, `resources/list`, `prompts/list`, and invocation requests.
2. `#[Tool]`, `#[Resource]`, and `#[Prompt]` attributes work for exposing application functionality with zero boilerplate.
3. Tool parameter schemas are auto-generated from PHP method signatures.
4. Both stdio and SSE transports work correctly.
5. Tool parameter validation prevents malformed invocations.
6. Authentication gates access to the MCP server.
7. Testing utilities allow verifying MCP tool behavior without running a server.
