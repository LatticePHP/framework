# MCP Module -- Task List

## Phase 1: Core Protocol

### MCP Server Implementation (JSON-RPC 2.0 Message Handling)
- [ ] Implement JSON-RPC 2.0 message parser (parse request objects from JSON)
- [ ] Implement JSON-RPC 2.0 response builder (construct result and error response objects)
- [ ] Support single requests and batch requests (array of request objects)
- [ ] Handle `initialize` method: accept client capabilities, return server capabilities
- [ ] Handle `initialized` notification: mark session as fully initialized
- [ ] Handle `ping` method: return empty result for keepalive
- [ ] Handle `tools/list` method: return registered tools with schemas
- [ ] Handle `tools/call` method: execute tool and return result
- [ ] Handle `resources/list` method: return registered resources
- [ ] Handle `resources/read` method: read resource content by URI
- [ ] Handle `prompts/list` method: return registered prompt templates
- [ ] Handle `prompts/get` method: render prompt template with arguments
- [ ] Return JSON-RPC error codes for invalid method, invalid params, internal error
- [ ] Support request cancellation via `$/cancelRequest` notification
- [ ] Unit tests for JSON-RPC message parsing and response construction
- [ ] Unit tests for each MCP method handler

### `#[Tool]` Attribute on Service Methods
- [ ] Define `#[Tool]` attribute with parameters: name (optional, defaults to method name), description
- [ ] Support applying `#[Tool]` to public methods on any service class
- [ ] Auto-extract tool name from method name if not provided
- [ ] Auto-extract tool description from attribute or docblock `@description` tag
- [ ] Register attributed tools in the tool registry at compile time
- [ ] Unit tests for tool attribute discovery and registration

### `#[Resource]` Attribute on Methods
- [ ] Define `#[Resource]` attribute with parameters: uri (URI template), name, description, mimeType
- [ ] Support applying `#[Resource]` to public methods on any service class
- [ ] Support static resources (fixed URI) and dynamic resources (URI with template variables)
- [ ] Auto-extract resource name from method name if not provided
- [ ] Register attributed resources in the resource registry at compile time
- [ ] Unit tests for resource attribute discovery and registration

### `#[Prompt]` Attribute on Methods
- [ ] Define `#[Prompt]` attribute with parameters: name (optional, defaults to method name), description
- [ ] Support applying `#[Prompt]` to public methods that return prompt message arrays
- [ ] Auto-extract prompt arguments from method parameters
- [ ] Support argument descriptions via `#[PromptArgument]` attribute or docblock
- [ ] Register attributed prompts in the prompt registry at compile time
- [ ] Unit tests for prompt attribute discovery and registration

### Tool Parameter Schema Auto-Generation from PHP Method Signatures
- [ ] Extract parameter names, types, and nullability from method signature reflection
- [ ] Map PHP `string` to JSON Schema `{ "type": "string" }`
- [ ] Map PHP `int` to JSON Schema `{ "type": "integer" }`
- [ ] Map PHP `float` to JSON Schema `{ "type": "number" }`
- [ ] Map PHP `bool` to JSON Schema `{ "type": "boolean" }`
- [ ] Map PHP `array` to JSON Schema `{ "type": "array" }` (with item type from docblock if available)
- [ ] Map PHP backed enums to JSON Schema `{ "type": "string", "enum": [...] }`
- [ ] Mark parameters without default values as `required`
- [ ] Include default values in schema when present
- [ ] Extract parameter descriptions from docblock `@param` tags
- [ ] Support `#[ToolParam]` attribute for description and constraint overrides
- [ ] Generate complete JSON Schema `inputSchema` for each tool
- [ ] Unit tests for schema generation from various method signatures

### Tool Execution with Input Validation
- [ ] Receive tool call request with tool name and arguments object
- [ ] Resolve tool method from registry by name
- [ ] Validate arguments against the tool's input schema before execution
- [ ] Return validation errors as JSON-RPC invalid params error
- [ ] Invoke tool method with validated arguments via dependency injection
- [ ] Catch exceptions thrown by tool methods and return as tool error results
- [ ] Return tool result as content array (text, image, or resource content blocks)
- [ ] Support returning `isError: true` for tool-level errors distinct from protocol errors
- [ ] Unit tests for tool execution with valid arguments, invalid arguments, and exceptions

### Resource Reading with URI Template Matching
- [ ] Implement URI template matching for resource lookup
- [ ] Support simple URI paths (`config://app/settings`)
- [ ] Support URI templates with variables (`users://{userId}/profile`)
- [ ] Extract template variables from request URI and pass to resource method
- [ ] Invoke resource method with extracted variables via dependency injection
- [ ] Return resource content with URI, MIME type, and text or blob content
- [ ] Support listing resource templates (resources with URI variables)
- [ ] Unit tests for URI template matching, variable extraction, and content return

### Prompt Template Rendering
- [ ] Receive prompt get request with prompt name and arguments
- [ ] Resolve prompt method from registry by name
- [ ] Validate prompt arguments against expected parameters
- [ ] Invoke prompt method with arguments to produce message array
- [ ] Return prompt messages in MCP prompt format (role + content pairs)
- [ ] Support multi-message prompts (system + user message sequences)
- [ ] Unit tests for prompt rendering with various argument combinations

### Capability Negotiation (Tools, Resources, Prompts)
- [ ] On `initialize`, advertise server capabilities based on registered features
- [ ] Include `tools` capability if any tools are registered
- [ ] Include `resources` capability if any resources are registered (with `listChanged` if applicable)
- [ ] Include `prompts` capability if any prompts are registered (with `listChanged` if applicable)
- [ ] Accept and store client capabilities from initialize request
- [ ] Return server name, version, and protocol version in initialize response
- [ ] Reject requests before initialize is complete (except `initialize` itself)
- [ ] Unit tests for capability negotiation with various feature combinations

### McpModule and McpServiceProvider
- [ ] Create `McpModule` with `#[Module]` attribute
- [ ] Create `McpServiceProvider` implementing provider interface
- [ ] Register MCP server, tool registry, resource registry, prompt registry
- [ ] Register transport implementations (stdio, SSE)
- [ ] Load configuration from `config/mcp.php` config file
- [ ] Register CLI commands for MCP operations
- [ ] Auto-discover and register tools/resources/prompts from compiled attributes
- [ ] Unit tests for module and provider registration


## Phase 2: Transports

### stdio Transport (For CLI-Based MCP Servers)
- [ ] Implement stdio transport that reads JSON-RPC from stdin and writes to stdout
- [ ] Parse newline-delimited JSON messages from stdin
- [ ] Write JSON-RPC responses to stdout followed by newline
- [ ] Handle continuous message loop (read -> process -> respond -> repeat)
- [ ] Support graceful shutdown on EOF or SIGTERM
- [ ] Buffer partial reads for incomplete JSON messages
- [ ] Log transport activity to stderr (not stdout, which is the protocol channel)
- [ ] Unit tests for message reading, writing, and loop lifecycle

### SSE Transport (For HTTP-Based MCP Servers)
- [ ] Implement SSE transport for HTTP-based MCP communication
- [ ] Create SSE endpoint that sends server events to connected clients
- [ ] Create POST endpoint for clients to send JSON-RPC requests
- [ ] Set appropriate headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`
- [ ] Send JSON-RPC responses as SSE `message` events
- [ ] Send keepalive comments at configurable interval to maintain connection
- [ ] Handle client disconnection and cleanup
- [ ] Unit tests for SSE message formatting and endpoint behavior

### McpController for SSE Endpoint
- [ ] Create `McpController` with SSE connection endpoint (`GET /mcp/sse`)
- [ ] Create message receive endpoint (`POST /mcp/message`)
- [ ] Route prefix configurable via `mcp.prefix` config (default `/mcp`)
- [ ] Validate content type on POST requests (must be `application/json`)
- [ ] Apply authentication middleware to both endpoints
- [ ] Unit tests for controller routing and request handling

### Session Management
- [ ] Generate unique session ID for each client connection
- [ ] Track active sessions with their capabilities and state
- [ ] Associate incoming requests with the correct session
- [ ] Clean up session state on client disconnection
- [ ] Support session timeout for idle connections
- [ ] Unit tests for session creation, tracking, and cleanup

### Connection Lifecycle (initialize, initialized, shutdown)
- [ ] Enforce connection lifecycle: must `initialize` before any other method
- [ ] Track session state: `connecting` -> `initializing` -> `ready` -> `shutting_down` -> `closed`
- [ ] Handle `shutdown` request: stop accepting new requests, complete in-flight requests
- [ ] Handle `exit` notification: close connection after shutdown
- [ ] Return error for requests received in wrong lifecycle state
- [ ] Timeout connections that never complete initialization
- [ ] Unit tests for lifecycle state transitions and enforcement


## Phase 3: Integration

### Auto-Discover Tools from All Registered Modules
- [ ] At compile time, scan all registered modules for `#[Tool]`, `#[Resource]`, `#[Prompt]` attributes
- [ ] Aggregate tools, resources, and prompts from all modules into the MCP server
- [ ] Support module-level prefixing for tool names (optional, to avoid collisions)
- [ ] Support excluding specific modules from MCP exposure via config
- [ ] Re-discover on module registration changes (development mode)
- [ ] Unit tests for multi-module tool discovery and aggregation

### Guard Integration (Authenticate MCP Clients)
- [ ] Apply authentication guard to MCP transport endpoints
- [ ] Support API key authentication for MCP clients
- [ ] Support bearer token authentication
- [ ] Support custom authentication strategies via guard configuration
- [ ] Return JSON-RPC error for unauthorized requests (not HTTP 401)
- [ ] Per-tool authorization: support `#[McpGuard(AdminGuard::class)]` on tool methods
- [ ] Unit tests for authentication with valid and invalid credentials

### Logging All Tool Invocations
- [ ] Log every tool invocation with: tool name, arguments (sanitized), client session, timestamp
- [ ] Log tool results with: success/error, duration (ms), result size
- [ ] Support configurable log level (debug, info) and log channel
- [ ] Support disabling logging for specific tools via attribute parameter
- [ ] Emit `McpToolInvoked` event via `lattice/events` for custom observability
- [ ] Unit tests for logging output format

### Rate Limiting MCP Calls
- [ ] Integrate with `lattice/rate-limit` for per-client rate limiting
- [ ] Support requests-per-minute limits per session
- [ ] Support per-tool rate limits via attribute parameter
- [ ] Return JSON-RPC error with retry information when rate limited
- [ ] Unit tests for rate limiting behavior

### Tool Result Caching
- [ ] Support `#[McpCache(ttl: 300)]` attribute on tool methods for result caching
- [ ] Cache key based on tool name and arguments hash
- [ ] Use `lattice/cache` for cache storage
- [ ] Support cache invalidation via explicit flush or TTL expiry
- [ ] Do not cache tools that have side effects (opt-in caching only)
- [ ] Unit tests for cache hit/miss behavior


## Phase 4: Developer Experience

### CLI Command: `php lattice mcp:serve` (Start stdio Server)
- [ ] Implement `mcp:serve` CLI command that starts the MCP server in stdio mode
- [ ] Read JSON-RPC messages from stdin, write responses to stdout
- [ ] Support `--transport=stdio|sse` flag (default stdio)
- [ ] Support `--port=8080` flag for SSE transport HTTP server
- [ ] Display startup banner with server name, version, and registered capabilities count
- [ ] Support graceful shutdown via Ctrl+C / SIGTERM
- [ ] Unit tests for command initialization and flag parsing

### CLI Command: `php lattice mcp:list` (Show Registered Tools/Resources/Prompts)
- [ ] Implement `mcp:list` CLI command that displays all registered MCP capabilities
- [ ] List tools with: name, description, parameter count
- [ ] List resources with: URI template, name, MIME type
- [ ] List prompts with: name, description, argument count
- [ ] Support `--type=tools|resources|prompts` filter flag
- [ ] Support `--json` flag for machine-readable output
- [ ] Display summary counts at the bottom
- [ ] Unit tests for command output format

### Testing Utilities (FakeMcpClient)
- [ ] Implement `FakeMcpClient` that sends requests to the MCP server in-process (no transport)
- [ ] Support `callTool(string $name, array $arguments): McpToolResult`
- [ ] Support `readResource(string $uri): McpResourceContent`
- [ ] Support `getPrompt(string $name, array $arguments): array`
- [ ] Support `listTools(): array`, `listResources(): array`, `listPrompts(): array`
- [ ] Record all invocations for assertion: `assertToolCalled(string $name, int $times)`
- [ ] Support asserting tool arguments: `assertToolCalledWith(string $name, array $args)`
- [ ] Unit tests for the fake client itself

### Documentation
- [ ] Write getting-started guide with installation, configuration, and first tool
- [ ] Document `#[Tool]` attribute with parameter schema examples
- [ ] Document `#[Resource]` attribute with URI template examples
- [ ] Document `#[Prompt]` attribute with multi-message prompt examples
- [ ] Document stdio transport setup for Claude Desktop integration
- [ ] Document SSE transport setup for web-based clients
- [ ] Document authentication and authorization configuration
- [ ] Document rate limiting and caching configuration
- [ ] Document testing with `FakeMcpClient`
- [ ] Document CLI commands (`mcp:serve`, `mcp:list`)

### Example: Expose CRM Operations as MCP Tools
- [ ] Create example `CrmToolProvider` class with `#[Tool]` methods
- [ ] Example tool: `searchContacts(string $query, int $limit = 10)` -- search CRM contacts
- [ ] Example tool: `getContact(int $id)` -- get contact details
- [ ] Example tool: `createNote(int $contactId, string $content)` -- add note to contact
- [ ] Example resource: `contacts://{id}` -- read contact as resource
- [ ] Example prompt: `summarizeContact` -- prompt template for summarizing a contact
- [ ] Document the example with step-by-step walkthrough
- [ ] Include Claude Desktop configuration snippet for connecting to the example server
