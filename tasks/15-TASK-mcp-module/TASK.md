# 15 — MCP Module (Model Context Protocol Server)

> Build an MCP server package: JSON-RPC 2.0 protocol, `#[Tool]` / `#[Resource]` / `#[Prompt]` attributes with auto-schema generation, stdio and SSE transports, module-level tool discovery, guard auth, logging, rate limiting, caching, CLI commands, testing utilities

## Dependencies
- Wave 1-3 packages complete
- Packages: `packages/core/`, `packages/module/`, `packages/http/`, `packages/serializer/`, `packages/compiler/`
- Optional: `packages/events/`, `packages/cache/`, `packages/rate-limit/`

## Subtasks

### 1. [ ] Core protocol — JSON-RPC 2.0 server, capability negotiation
- Implement JSON-RPC 2.0 message parser: parse request objects from JSON, support single and batch requests
- Implement JSON-RPC 2.0 response builder: construct result and error response objects
- Handle MCP protocol methods:
  - `initialize`: accept client capabilities, return server capabilities (tools, resources, prompts)
  - `initialized`: mark session as fully initialized
  - `ping`: return empty result for keepalive
  - `tools/list`: return registered tools with schemas
  - `tools/call`: execute tool and return result
  - `resources/list`: return registered resources
  - `resources/read`: read resource content by URI
  - `prompts/list`: return registered prompt templates
  - `prompts/get`: render prompt template with arguments
- Return JSON-RPC error codes for invalid method, invalid params, internal error
- Support request cancellation via `$/cancelRequest` notification
- Capability negotiation: on `initialize`, advertise capabilities based on registered features; accept and store client capabilities; return server name, version, protocol version
- Reject requests before `initialize` is complete (except `initialize` itself)

#### Detailed Steps
1. Create `Protocol/JsonRpcParser.php` parsing JSON to `JsonRpcRequest` objects (method, params, id)
2. Create `Protocol/JsonRpcResponseBuilder.php` building success and error response JSON
3. Create `Server/McpServer.php` as the central dispatcher: receives parsed request, routes to handler by method name
4. Create `Handlers/InitializeHandler.php`, `Handlers/ToolsListHandler.php`, `Handlers/ToolsCallHandler.php`, `Handlers/ResourcesListHandler.php`, `Handlers/ResourcesReadHandler.php`, `Handlers/PromptsListHandler.php`, `Handlers/PromptsGetHandler.php`, `Handlers/PingHandler.php`
5. Create `Server/CapabilityManager.php` building server capabilities from registered features and tracking client capabilities
6. Create `Server/SessionState.php` enum: connecting, initializing, ready, shutting_down, closed
7. Unit tests for JSON-RPC parsing and response building, each method handler, capability negotiation, pre-initialize rejection

#### Verification
- [ ] JSON-RPC parser correctly handles single requests, batch requests, and malformed JSON
- [ ] `initialize` returns server capabilities matching registered tools, resources, and prompts
- [ ] `tools/list` returns all registered tools with their schemas
- [ ] `ping` returns empty result
- [ ] Requests before `initialize` return error (except `initialize` itself)

### 2. [ ] `#[Tool]` attribute — auto-schema from method signatures, tool execution, input validation
- Define `#[Tool]` attribute with parameters: name (optional, defaults to method name), description
- Support applying to public methods on any service class
- Auto-extract tool name from method name if not provided; description from attribute or docblock

#### Tool Parameter Schema Auto-Generation
- Extract parameter names, types, nullability from method signature
- Map PHP types to JSON Schema: `string` -> `"string"`, `int` -> `"integer"`, `float` -> `"number"`, `bool` -> `"boolean"`, `array` -> `"array"`, backed enums -> `"string"` with `enum` values
- Mark required parameters (no default value), include default values in schema
- Extract parameter descriptions from `@param` docblock tags
- Support `#[ToolParam]` attribute for description and constraint overrides
- Generate complete `inputSchema` per tool

#### Tool Execution
- Receive `tools/call` request with tool name and arguments
- Resolve tool method from registry, validate arguments against input schema
- Return validation errors as JSON-RPC `invalid params` error
- Invoke tool method with validated arguments via dependency injection
- Catch exceptions and return as tool error results (`isError: true`)
- Return result as content array (text, image, or resource content blocks)

#### Detailed Steps
1. Create `Attributes/Tool.php` and `Attributes/ToolParam.php` attributes
2. Create `Tools/ToolRegistry.php` storing discovered tools with metadata and schema
3. Create `Tools/ToolSchemaGenerator.php` reflecting method signatures to produce JSON Schema
4. Create `Tools/ToolExecutor.php` handling validation, invocation via DI, error wrapping
5. Register tools at compile time from attributed methods
6. Unit tests for attribute discovery, schema generation (all PHP types), tool execution (valid args, invalid args, exceptions)

#### Verification
- [ ] `#[Tool]` attribute registers method in tool registry with correct name and description
- [ ] Schema generator maps PHP `string`, `int`, `float`, `bool`, `array`, `enum` to correct JSON Schema types
- [ ] Required parameters (no defaults) are marked required in schema
- [ ] Tool execution validates arguments and rejects invalid input with error
- [ ] Tool exceptions are caught and returned as `isError: true` results

### 3. [ ] `#[Resource]` attribute — URI templates, resource reading
- Define `#[Resource]` attribute with parameters: uri (URI template), name, description, mimeType
- Support static resources (fixed URI like `config://app/settings`) and dynamic resources (URI with variables like `users://{userId}/profile`)
- Auto-extract name from method name if not provided
- Register at compile time

#### Resource Reading
- Implement URI template matching for resource lookup
- Extract template variables from request URI and pass to resource method
- Invoke resource method with extracted variables via dependency injection
- Return resource content with URI, MIME type, and text or blob content
- Support listing resource templates (resources with URI variables)

#### Detailed Steps
1. Create `Attributes/Resource.php` attribute
2. Create `Resources/ResourceRegistry.php` storing discovered resources with URI patterns
3. Create `Resources/UriTemplateMatcher.php` matching request URIs to registered templates and extracting variables
4. Create `Resources/ResourceReader.php` invoking resource methods and formatting content response
5. Unit tests for URI template matching (static, dynamic, multi-variable), variable extraction, content reading

#### Verification
- [ ] `#[Resource]` registers method with URI template in resource registry
- [ ] Static URI `config://app/settings` matches exactly
- [ ] Dynamic URI `users://42/profile` matches template `users://{userId}/profile` and extracts `userId=42`
- [ ] Resource reading invokes method with extracted variables and returns content with MIME type
- [ ] `resources/list` includes resource templates with variable descriptions

### 4. [ ] `#[Prompt]` attribute — prompt templates, argument rendering
- Define `#[Prompt]` attribute with parameters: name (optional, defaults to method name), description
- Support applying to public methods that return prompt message arrays
- Auto-extract prompt arguments from method parameters
- Support `#[PromptArgument]` attribute for argument descriptions
- Register at compile time

#### Prompt Rendering
- Receive `prompts/get` request with name and arguments
- Resolve prompt method, validate arguments against expected parameters
- Invoke method to produce message array
- Return messages in MCP format (role + content pairs)
- Support multi-message prompts (system + user message sequences)

#### Detailed Steps
1. Create `Attributes/Prompt.php` and `Attributes/PromptArgument.php` attributes
2. Create `Prompts/PromptRegistry.php` storing discovered prompts with argument metadata
3. Create `Prompts/PromptRenderer.php` validating arguments and invoking prompt methods
4. Unit tests for prompt discovery, argument validation, rendering with various argument combinations

#### Verification
- [ ] `#[Prompt]` registers method with name and argument metadata in prompt registry
- [ ] `prompts/list` returns all prompts with descriptions and argument info
- [ ] `prompts/get` with valid arguments renders prompt messages correctly
- [ ] Multi-message prompts return system + user message sequences
- [ ] Missing required arguments return JSON-RPC error

### 5. [ ] Transports — stdio + SSE, McpController, session management

#### stdio Transport
- Read JSON-RPC from stdin, write to stdout (newline-delimited JSON)
- Continuous message loop: read -> process -> respond -> repeat
- Graceful shutdown on EOF or SIGTERM
- Buffer partial reads for incomplete JSON messages
- Log to stderr (stdout is the protocol channel)

#### SSE Transport
- SSE endpoint sending server events to connected clients (`GET /mcp/sse`)
- POST endpoint for client JSON-RPC requests (`POST /mcp/message`)
- Headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`
- JSON-RPC responses as SSE `message` events, keepalive heartbeat comments
- Handle client disconnect and cleanup

#### McpController
- `GET /mcp/sse` for SSE connection, `POST /mcp/message` for requests
- Route prefix configurable via `mcp.prefix` config (default `/mcp`)
- Validate content type on POST (must be `application/json`)
- Apply authentication middleware

#### Session Management
- Generate unique session ID per client connection
- Track active sessions with capabilities and state
- Associate incoming requests with correct session
- Clean up state on disconnect, timeout idle connections

#### Connection Lifecycle
- Enforce: must `initialize` before other methods
- State transitions: connecting -> initializing -> ready -> shutting_down -> closed
- Handle `shutdown` request (stop new requests, complete in-flight), `exit` notification (close)
- Return error for wrong-state requests, timeout connections that never complete initialization

#### Detailed Steps
1. Create `Transports/StdioTransport.php` with read/write loop and signal handling
2. Create `Transports/SseTransport.php` with SSE event streaming
3. Create `Http/McpController.php` with SSE and message endpoints
4. Create `Sessions/SessionManager.php` tracking active sessions with state and cleanup
5. Create `Sessions/SessionState.php` enum managing lifecycle transitions
6. Unit tests for stdio message loop, SSE formatting, controller routing, session lifecycle

#### Verification
- [ ] stdio transport reads JSON-RPC from stdin, processes, writes response to stdout
- [ ] SSE transport streams events and handles keepalive heartbeat
- [ ] McpController routes SSE connections and POST messages correctly
- [ ] Session ID is generated per connection and tracked
- [ ] Connection lifecycle enforces initialization before other methods
- [ ] Idle session timeout triggers cleanup

### 6. [ ] Integration — auto-discover tools from modules, guard auth, logging, rate limiting, caching

#### Auto-Discovery
- At compile time, scan all registered modules for `#[Tool]`, `#[Resource]`, `#[Prompt]` attributes
- Aggregate from all modules into the MCP server
- Support module-level prefixing for tool names (optional, avoid collisions)
- Support excluding specific modules from MCP exposure via config
- Re-discover on module changes (development mode)

#### Guard Authentication
- Apply authentication guard to MCP transport endpoints
- Support API key authentication, bearer token, custom strategies via guard config
- Return JSON-RPC error for unauthorized (not HTTP 401)
- Per-tool authorization: `#[McpGuard(AdminGuard::class)]` on tool methods

#### Logging
- Log every tool invocation: tool name, arguments (sanitized), client session, timestamp
- Log results: success/error, duration (ms), result size
- Configurable log level and channel
- Support disabling per tool via attribute parameter
- Emit `McpToolInvoked` event via `lattice/events`

#### Rate Limiting
- Per-client rate limiting via `lattice/rate-limit` (requests per minute)
- Per-tool rate limits via attribute parameter
- Return JSON-RPC error with retry information

#### Tool Result Caching
- `#[McpCache(ttl: 300)]` attribute on tool methods
- Cache key: tool name + arguments hash via `lattice/cache`
- Support invalidation via TTL or explicit flush
- Opt-in only (no caching of side-effect tools)

#### McpModule and McpServiceProvider
- `McpModule` with `#[Module]`: register service provider, transports, CLI commands, auto-discover attributes
- `McpServiceProvider`: register MCP server, registries, transports, load `config/mcp.php`

#### Detailed Steps
1. Create compile-time scanner that collects `#[Tool]`, `#[Resource]`, `#[Prompt]` from all module service classes
2. Create `Auth/McpGuardMiddleware.php` wrapping transport endpoints
3. Create `Attributes/McpGuard.php` attribute for per-tool authorization
4. Create `Logging/ToolInvocationLogger.php` recording invocations
5. Create `Events/McpToolInvoked.php` event
6. Create `RateLimit/McpRateLimiter.php` wrapping `lattice/rate-limit`
7. Create `Attributes/McpCache.php` attribute and `Cache/ToolResultCache.php`
8. Create `McpModule.php` and `McpServiceProvider.php`
9. Unit tests for multi-module discovery, auth (valid/invalid), logging, rate limiting, cache hit/miss

#### Verification
- [ ] Tools from multiple modules are discovered and aggregated in MCP server
- [ ] Module prefix avoids name collisions (`module.toolName`)
- [ ] Invalid API key returns JSON-RPC authentication error
- [ ] `#[McpGuard(AdminGuard::class)]` blocks unauthorized tool calls
- [ ] Tool invocations are logged with name, arguments, duration, and result
- [ ] Rate limit returns JSON-RPC error when exceeded
- [ ] `#[McpCache(ttl: 300)]` returns cached result on repeat calls within TTL

### 7. [ ] DX — `php lattice mcp:serve`, `php lattice mcp:list`, FakeMcpClient, docs + tests

#### CLI: `php lattice mcp:serve`
- Start MCP server in stdio mode (default)
- `--transport=stdio|sse` flag, `--port=8080` for SSE HTTP server
- Display startup banner with server name, version, registered capability counts
- Graceful shutdown via Ctrl+C / SIGTERM

#### CLI: `php lattice mcp:list`
- Display all registered tools, resources, and prompts
- Tools: name, description, parameter count
- Resources: URI template, name, MIME type
- Prompts: name, description, argument count
- `--type=tools|resources|prompts` filter, `--json` flag
- Summary counts at bottom

#### FakeMcpClient
- In-process client (no transport) for testing
- `callTool(string $name, array $arguments): McpToolResult`
- `readResource(string $uri): McpResourceContent`
- `getPrompt(string $name, array $arguments): array`
- `listTools()`, `listResources()`, `listPrompts()`
- Record all invocations: `assertToolCalled(name, times)`, `assertToolCalledWith(name, args)`

#### Documentation
- Getting-started guide: installation, configuration, first tool
- `#[Tool]` with parameter schema examples
- `#[Resource]` with URI template examples
- `#[Prompt]` with multi-message examples
- stdio transport setup for Claude Desktop integration
- SSE transport setup for web-based clients
- Authentication and authorization configuration
- Rate limiting and caching configuration
- Testing with `FakeMcpClient`
- CLI commands documentation

#### Example
- Create example `CrmToolProvider` with `#[Tool]` methods: `searchContacts`, `getContact`, `createNote`
- Example resource: `contacts://{id}`
- Example prompt: `summarizeContact`
- Include Claude Desktop configuration snippet

#### Detailed Steps
1. Create `Commands/McpServeCommand.php` with transport selection and startup banner
2. Create `Commands/McpListCommand.php` with formatted output and JSON flag
3. Create `Testing/FakeMcpClient.php` with in-process invocation and assertion helpers
4. Write documentation for all features with code examples
5. Create `Examples/CrmToolProvider.php` with annotated example tools/resources/prompts
6. Unit tests for CLI commands (flag parsing, output format), FakeMcpClient (all methods, assertions)

#### Verification
- [ ] `mcp:serve` starts server and handles JSON-RPC messages
- [ ] `mcp:list` displays all tools, resources, and prompts in formatted table
- [ ] `mcp:list --json` outputs machine-readable JSON
- [ ] `FakeMcpClient::callTool()` executes tool in-process and returns result
- [ ] `FakeMcpClient::assertToolCalled('getContact', 2)` verifies invocation count
- [ ] `FakeMcpClient::assertToolCalledWith('createNote', ['contactId' => 1, 'content' => '...'])` verifies arguments
- [ ] Documentation covers all features with working examples

## Integration Verification
- [ ] MCP client connects via stdio, receives capabilities, lists tools
- [ ] `tools/call` executes tool with validated input and returns result
- [ ] Invalid tool arguments are rejected with JSON-RPC validation error
- [ ] `resources/read` returns resource content for valid URI
- [ ] `prompts/get` renders prompt template with arguments
- [ ] Guard authentication blocks unauthorized MCP clients
- [ ] Tools from multiple modules are available via MCP
- [ ] `FakeMcpClient` enables testing tool behavior without running a server
