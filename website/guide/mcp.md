---
outline: deep
---

# MCP Server

LatticePHP includes a Model Context Protocol (MCP) server through the `lattice/mcp` package. This allows AI assistants (Claude, GPT, Copilot) to interact with your application as tools, read resources, and use prompts.

## Overview

MCP exposes three types of capabilities:

| Type | Attribute | Purpose |
|---|---|---|
| **Tools** | `#[Tool]` | Actions the AI can execute (query database, create records, run commands) |
| **Resources** | `#[Resource]` | Data the AI can read (configuration, schemas, documentation) |
| **Prompts** | `#[Prompt]` | Reusable prompt templates the AI can use |

## Defining Tools

Annotate methods with `#[Tool]` to expose them as MCP tools:

```php
<?php
declare(strict_types=1);

use Lattice\Mcp\Attributes\Tool;

final class ContactTools
{
    #[Tool(description: 'Search contacts by name or email')]
    public function searchContacts(string $query, int $limit = 10): array
    {
        return Contact::search($query)->take($limit)->get()->toArray();
    }

    #[Tool(description: 'Get contact details by ID')]
    public function getContact(int $id): array
    {
        return Contact::findOrFail($id)->toArray();
    }

    #[Tool(description: 'Create a new contact')]
    public function createContact(string $name, string $email, string $status = 'lead'): array
    {
        $contact = Contact::create(compact('name', 'email', 'status'));
        return $contact->toArray();
    }
}
```

The `ToolSchemaGenerator` automatically generates JSON Schema for tool parameters from your method signatures.

## Defining Resources

Resources expose read-only data:

```php
use Lattice\Mcp\Attributes\Resource;

final class AppResources
{
    #[Resource(description: 'Application configuration')]
    public function config(): array
    {
        return [
            'name' => config('app.name'),
            'env' => config('app.env'),
            'modules' => $this->app->getModuleDefinitions(),
        ];
    }

    #[Resource(description: 'Database schema for all tables')]
    public function schema(): array
    {
        return $this->schemaInspector->getTables();
    }
}
```

## Defining Prompts

Prompts are reusable templates:

```php
use Lattice\Mcp\Attributes\Prompt;
use Lattice\Mcp\Attributes\PromptArgument;

final class AppPrompts
{
    #[Prompt(description: 'Generate a migration for a new table')]
    public function migrationPrompt(
        #[PromptArgument] string $tableName,
        #[PromptArgument] string $columns,
    ): string {
        return "Generate a LatticePHP migration for the '{$tableName}' table with columns: {$columns}. "
             . "Use Illuminate Schema Builder. Follow the project conventions.";
    }
}
```

## Module Registration

```php
use Lattice\Mcp\McpModule;

#[Module(
    imports: [McpModule::class],
    providers: [ContactTools::class, AppResources::class, AppPrompts::class],
)]
final class AppModule {}
```

## Starting the Server

```bash
# Start MCP server (stdio transport for local AI tools)
php bin/lattice mcp:serve

# Start with SSE transport (for web-based AI clients)
php bin/lattice mcp:serve --transport=sse

# List all registered tools, resources, and prompts
php bin/lattice mcp:list
```

## Transports

| Transport | Use Case |
|---|---|
| **Stdio** | Local AI tools (Claude Code, Cursor) |
| **SSE** | Web-based AI clients |

## JSON-RPC Protocol

MCP uses JSON-RPC 2.0. The `JsonRpcServer` handles request routing:

```json
// Client request
{"jsonrpc": "2.0", "method": "tools/call", "params": {"name": "searchContacts", "arguments": {"query": "alice"}}, "id": 1}

// Server response
{"jsonrpc": "2.0", "result": {"content": [{"type": "text", "text": "[{\"id\": 1, \"name\": \"Alice\"}]"}]}, "id": 1}
```

## Capability Negotiation

The `CapabilityNegotiator` handles the MCP handshake, advertising which tools, resources, and prompts are available.

## Testing

```php
use Lattice\Mcp\Testing\FakeMcpClient;

$client = new FakeMcpClient($server);

$result = $client->callTool('searchContacts', ['query' => 'alice']);
$this->assertNotEmpty($result);
```

## Next Steps

- [Catalyst](catalyst.md) -- AI development accelerator with MCP tools
- [CLI Commands](cli.md) -- `mcp:serve` and `mcp:list` commands
- [OpenAPI Generation](openapi.md) -- auto-generate API specs
