# 14 — AI Module (Multi-Provider AI SDK)

> Build a comprehensive multi-provider AI SDK: AiManager with provider registry, 5 v1.0 providers (Anthropic, OpenAI, Gemini, Ollama, Cohere), chat/streaming/tools/structured output, image generation, audio, embeddings, reranking, autonomous agents with `#[Agent]` attribute, middleware pipeline, conversation stores, FakeProvider for testing, CLI REPL

**Phased provider rollout:** v1.0 = Anthropic, OpenAI, Gemini, Ollama, Cohere. v1.1 = Azure OpenAI, DeepSeek, Groq, Mistral, OpenRouter. v2.0 = xAI, ElevenLabs, Jina, VoyageAI.

## Dependencies
- Wave 1-3 packages complete
- Packages: `packages/core/`, `packages/http-client/`, `packages/contracts/`, `packages/events/`, `packages/queue/`, `packages/cache/`, `packages/rate-limit/`, `packages/database/`, `packages/compiler/`, `packages/module/`

## Subtasks

### 1. [ ] Core — AiManager, provider interface, message/response value objects, config

#### AiManager
- Implement `AiManager` as the primary entry point for all AI operations
- Register providers by name in an internal registry (`string $name => AiProviderInterface`)
- Resolve default provider from config (`ai.default`)
- Implement `provider(string $name)` to retrieve specific provider, `getDefaultProvider()` for default
- Delegate top-level methods (`chat`, `stream`, `structured`, `embed`, `image`, `audio`, `transcribe`, `rerank`) to the default provider
- Support dynamic provider switching at runtime via `using(string $provider)`
- Throw `ProviderNotFoundException` for unregistered names

#### AiProviderInterface
- Define with methods: `chat()`, `stream()`, `structured()`, `embed()`, `embedBatch()`, `generateImage()`, `synthesize()`, `transcribe()`, `rerank()`
- Define `ProviderCapability` enum: Chat, Streaming, ToolCalling, StructuredOutput, Embeddings, ImageGeneration, AudioSynthesis, AudioTranscription, Reranking, Vision, FileHandling
- Add `capabilities(): array` and `supports(ProviderCapability): bool` methods
- Throw `UnsupportedCapabilityException` for unsupported operations

#### Message Value Objects
- `UserMessage` (text, optional images/files), `AssistantMessage` (text, optional tool calls), `SystemMessage` (system instructions)
- `ToolCall` (id, tool name, arguments), `ToolResult` (tool call id, result data, is error flag)
- Support multipart content on `UserMessage` (text + images for vision), file attachments
- Factory methods: `UserMessage::create()`, `SystemMessage::create()`, `AssistantMessage::withToolCalls()`
- `MessageCollection` for ordered message lists
- JSON serialization/deserialization on all types

#### Response Value Objects
- `AiResponse` (text content, usage, finish reason, tool calls, model ID, raw response)
- `StreamChunk` (delta text, accumulated text, finish reason, partial usage, tool call deltas)
- `Usage` (prompt tokens, completion tokens, total tokens)
- `FinishReason` enum: Stop, Length, ToolCall, ContentFilter, Error
- `EmbeddingResponse` (vector, model, usage), `ImageResponse` (URL or base64, revised prompt, model)
- `AudioResponse` (audio data, format, duration), `TranscriptionResponse` (text, segments, language, duration)
- `RerankResponse` (scored results: index + relevance score)
- Helper methods on `AiResponse`: `hasToolCalls()`, `getToolCalls()`, `isComplete()`, `getText()`, `getUsage()`

#### Configuration
- `AiConfig` value object with typed properties for all settings
- Per-provider config: API key, base URL, default model, organization ID, API version, timeout
- Global defaults: temperature, max tokens, top-p, stop sequences
- Middleware stack config (`ai.middleware`), store config (`ai.store`)
- Load from `config/ai.php`, validate at boot (missing API keys, invalid provider names)
- Mask API keys in debug output and error messages

#### AiServiceProvider and AiModule
- `AiServiceProvider`: register `AiConfig`, `AiManager` (singleton), all 15 provider implementations (lazy instantiation), default provider binding, middleware pipeline, store
- Publish `config/ai.php`
- `AiModule` with `#[Module]`: register CLI commands (`ai:chat`, `ai:models`, `ai:cost`), compile-time discovery of `#[AiTool]` and `#[Agent]` attributes
- `#[AiProvider]` attribute for DI injection: `#[AiProvider('anthropic')]` on constructor params or typed properties, container resolves correct provider

#### Detailed Steps
1. Create `AiManager.php` with provider registry map and delegation methods
2. Create `Contracts/AiProviderInterface.php` with full method signatures and capability enum
3. Create `Messages/UserMessage.php`, `Messages/AssistantMessage.php`, `Messages/SystemMessage.php`, `Messages/ToolCall.php`, `Messages/ToolResult.php`, `Messages/MessageCollection.php`
4. Create `Responses/AiResponse.php`, `Responses/StreamChunk.php`, `Responses/Usage.php`, `Responses/FinishReason.php`, `Responses/EmbeddingResponse.php`, `Responses/ImageResponse.php`, `Responses/AudioResponse.php`, `Responses/TranscriptionResponse.php`, `Responses/RerankResponse.php`
5. Create `Config/AiConfig.php` with validation, `config/ai.php` template
6. Create `AiServiceProvider.php` with lazy provider registration and `AiModule.php` with attribute discovery
7. Create `Attributes/AiProvider.php` attribute for DI
8. Unit tests for all value objects, serialization, manager delegation, config validation

#### Verification
- [ ] `AiManager` resolves default provider and delegates `chat()` correctly
- [ ] `provider('openai')` returns the OpenAI implementation, unknown name throws `ProviderNotFoundException`
- [ ] All message and response value objects serialize/deserialize round-trip correctly
- [ ] Config validation rejects missing API keys at boot time
- [ ] `#[AiProvider('anthropic')]` resolves to the Anthropic provider via DI

### 2. [ ] Provider base class — shared HTTP client, retry, timeout, SSE streaming
- Implement `AbstractProvider` base class with shared functionality
- Shared HTTP client configuration via `lattice/http-client` (base URL, headers, auth)
- Shared timeout handling (connect timeout, request timeout, configurable per provider)
- Shared retry logic with exponential backoff and jitter (configurable max retries, base delay, max delay)
- Respect `Retry-After` header; do not retry client errors (400, 401, 403) — only transient (429, 500, 502, 503, 529)
- Shared SSE stream parser for Server-Sent Events
- Shared error response normalization: map provider-specific errors to `AiException` subclasses
- Shared request/response logging hooks (delegate to middleware pipeline)

#### Detailed Steps
1. Create `Providers/AbstractProvider.php` with: `httpClient()`, `sendRequest()`, `streamRequest()`, `parseSSE()`, `handleError()`
2. Implement retry: `retryWithBackoff(Closure $request, int $maxRetries, float $baseDelay, float $maxDelay)`
3. Implement SSE parser: read lines, handle `data:`, `event:`, `id:` fields, yield parsed events
4. Create `Exceptions/AiException.php`, `Exceptions/RateLimitException.php`, `Exceptions/AuthenticationException.php`, `Exceptions/ContextLengthExceededException.php`
5. Unit tests for retry logic (succeeds after transient failure, does not retry 400), timeout, SSE parsing, error normalization

#### Verification
- [ ] Retry logic retries on 429/500/503 with exponential backoff
- [ ] Retry logic does NOT retry on 400/401/403
- [ ] SSE parser correctly yields events from server-sent event stream
- [ ] Provider-specific errors are normalized to typed `AiException` subclasses
- [ ] `Retry-After` header is respected when present

### 3. [ ] v1.0 providers — AnthropicProvider, OpenAiProvider, GeminiProvider, OllamaProvider, CohereProvider

#### AnthropicProvider (Claude)
- Chat completion via Messages API (`POST /v1/messages`)
- Streaming via SSE (`stream: true`)
- Tool/function calling (Anthropic `tool_use` format)
- Structured output via JSON mode / assistant prefill technique
- Vision support (images via base64 or URL)
- Handle API errors (429, 529, 400, 401), map stop reasons to `FinishReason` enum
- Support model selection (claude-opus, claude-sonnet, claude-haiku), API version header
- Support extended thinking when available

#### OpenAiProvider (GPT)
- Chat completion via Chat Completions API (`POST /v1/chat/completions`)
- Streaming via SSE, tool/function calling (OpenAI function/tool format)
- Structured output via JSON mode (`response_format: json_object`) and JSON schema mode
- Embeddings via Embeddings API (`POST /v1/embeddings`) — single and batch
- Image generation via Images API (`POST /v1/images/generations`)
- Audio synthesis via Audio Speech API (`POST /v1/audio/speech`)
- Audio transcription via Whisper (`POST /v1/audio/transcriptions`)
- Handle errors (rate limit, context length, content policy), map finish reasons, support org ID header
- Support model selection (gpt-4o, gpt-4o-mini, o1, o3)

#### GeminiProvider (Google)
- Chat via Generate Content API (`POST /v1/models/{model}:generateContent`)
- Streaming via SSE (`streamGenerateContent`)
- Tool/function calling (Gemini tool format), structured output via response schema
- Vision support, embeddings via Embedding API
- Handle errors (rate limit, safety filters), map finish reasons
- Support model selection (gemini-2.0-flash, gemini-2.0-pro, gemini-1.5-pro/flash)
- Support API key and OAuth authentication

#### OllamaProvider (Local Models)
- Chat via Ollama REST API (`POST /api/chat`)
- Streaming via newline-delimited JSON
- Tool/function calling where model supports, structured output via JSON format instruction
- Embeddings via `POST /api/embeddings`
- Configurable base URL (default `http://localhost:11434`), model selection from locally available
- Model pull status checking

#### CohereProvider
- Chat via Cohere Chat API (`POST /v2/chat`), streaming via SSE
- Tool/function calling
- Embeddings via Embed API (`POST /v2/embed`) — single and batch, with input type (search_document, search_query, classification, clustering)
- Reranking via Rerank API (`POST /v2/rerank`)
- Support model selection (command-r-plus, command-r, embed-v4, rerank-v3.5)

#### Detailed Steps
1. Create `Providers/AnthropicProvider.php`, `Providers/OpenAiProvider.php`, `Providers/GeminiProvider.php`, `Providers/OllamaProvider.php`, `Providers/CohereProvider.php` — each extending `AbstractProvider`
2. Each provider: implement all supported interface methods, map provider-specific request/response format to standard value objects
3. Each provider: throw `UnsupportedCapabilityException` for methods not supported (e.g., Ollama has no image generation)
4. Unit tests for each provider with mock HTTP responses for all operations

#### Verification
- [ ] Anthropic: chat, streaming, tools, structured output, and vision all work with mock responses
- [ ] OpenAI: chat, streaming, tools, structured output, embeddings, images, audio all work
- [ ] Gemini: chat, streaming, tools, structured output, vision, embeddings all work
- [ ] Ollama: chat, streaming, embeddings work against mock local API
- [ ] Cohere: chat, streaming, tools, embeddings, reranking all work
- [ ] Each provider throws `UnsupportedCapabilityException` for unsupported operations

### 4. [ ] Features — chat, streaming, tool calling with `#[AiTool]`, structured output

#### Chat Completion
- `AiManager::chat(array $messages, array $options = [])` — single message shorthand and full conversation
- Options: model, temperature, maxTokens, topP, stopSequences, tools, responseFormat
- System message as separate parameter for convenience

#### Streaming
- `AiManager::stream()` returning `Generator<StreamChunk>` yielding tokens as they arrive
- `onToken` callback support, `onComplete` callback
- Streaming tool calls: accumulate partial JSON across chunks, emit complete `ToolCall` on finish
- Handle stream interruption gracefully (return partial response)

#### Tool / Function Calling
- `ToolDefinition` class (name, description, parameters as JSON Schema)
- Pass tools to chat/stream via `tools` option
- Parse tool call responses, implement result passing back (append `ToolResult` and re-call)
- Multi-step tool loops: model calls tool -> result -> next tool or final response (max iterations configurable, default 10)
- Validate tool arguments against JSON Schema before execution
- Support parallel tool calls

#### `#[AiTool]` Attribute
- Define `#[AiTool]` with name and description parameters
- Apply to PHP methods; auto-extract parameters from method signature (name, type, nullable, default)
- Auto-generate JSON Schema from PHP types (string, int, float, bool, array, enum)
- Extract parameter descriptions from `@param` docblock tags
- Support `#[AiToolParam]` attribute for description and constraint overrides
- Register at compile time via `AiModule`
- Support tool classes with `__invoke` method

#### Structured Output
- `AiManager::structured(array $messages, string|array $schema, array $options = [])` — returns typed object
- Accept PHP class name (auto-generate schema from properties), `ObjectSchema` fluent builder, or raw JSON Schema
- Use provider-native JSON mode (OpenAI, Gemini) or assistant prefill (Anthropic)
- Validate response against schema, retry on malformed JSON (configurable max retries, default 2)

#### Detailed Steps
1. Create `Features/ChatManager.php` handling chat delegation and option merging
2. Create `Features/StreamManager.php` handling generator creation and callback routing
3. Create `Tools/ToolDefinition.php`, `Tools/ToolExecutor.php` (loop handler), `Tools/ToolValidator.php` (JSON Schema validation)
4. Create `Attributes/AiTool.php` and `Attributes/AiToolParam.php` attributes
5. Create `Tools/ToolSchemaGenerator.php` that reflects method signatures to produce JSON Schema
6. Create `Structured/StructuredOutputManager.php` with schema generation, provider dispatch, validation, and retry
7. Create `Structured/ObjectSchema.php` fluent builder
8. Unit tests for chat, streaming (chunk ordering, callbacks, tool accumulation), tool calling (round-trip, multi-step, validation, parallel), `#[AiTool]` discovery and schema generation, structured output (class targets, validation, retry)

#### Verification
- [ ] `chat('What is PHP?')` returns `AiResponse` with text content and usage
- [ ] `stream()` yields `StreamChunk` objects with delta and accumulated text
- [ ] Tool calling executes multi-step loops: model -> tool -> result -> model -> response
- [ ] `#[AiTool]` on a method auto-generates correct JSON Schema with parameter types and descriptions
- [ ] `structured()` returns a validated PHP object matching the requested schema

### 5. [ ] Features — image generation, audio synthesis/transcription, embeddings, reranking

#### Image Generation
- `AiManager::generateImage(string $prompt, array $options = [])` with size, quality, style, count, format options
- Image editing: `editImage(prompt, image, options)`, variations: `imageVariation(image, options)`
- Supported by OpenAI (DALL-E); throw `UnsupportedCapabilityException` for others

#### Audio Synthesis
- `AiManager::synthesize(string $text, array $options = [])` with voice, model, format (mp3, wav, opus, flac), speed
- Streaming audio output for long texts
- Supported by OpenAI TTS; return `AudioResponse` with binary data, format, duration

#### Audio Transcription
- `AiManager::transcribe(string|resource $audio, array $options = [])` with model, language hint, format (text, json, srt, vtt)
- File upload from path or stream, return `TranscriptionResponse` with text, segments, language, duration
- Supported by OpenAI Whisper

#### Embeddings
- `AiManager::embed(string $text, array $options = [])` and `embedBatch(array $texts, array $options = [])`
- Options: model, dimensions (where provider supports), input type (query vs. document for Cohere)
- Auto-chunk large batches beyond provider limits
- Supported by: OpenAI, Gemini, Ollama, Cohere

#### Reranking
- `AiManager::rerank(string $query, array $documents, array $options = [])` with model, top N, return documents flag
- Accept documents as strings or objects with text field
- Return `RerankResponse` with scored results (index + relevance score)
- Supported by: Cohere

#### File Handling
- `AiManager::uploadFile(string $path, array $options = [])` returning `FileReference`
- Reference files in `UserMessage` content, inline base64 fallback, MIME type detection, cleanup

#### Detailed Steps
1. Create `Features/ImageManager.php`, `Features/AudioManager.php`, `Features/EmbeddingManager.php`, `Features/RerankManager.php`, `Features/FileManager.php`
2. Each manager delegates to the active provider, checks capabilities, formats options
3. Embedding batch: split into provider-specific batch sizes, concatenate results
4. Unit tests with mock HTTP responses for all operations across all supporting providers

#### Verification
- [ ] `generateImage('a sunset')` returns `ImageResponse` with URL
- [ ] `synthesize('Hello world')` returns `AudioResponse` with audio data
- [ ] `transcribe($audioFile)` returns `TranscriptionResponse` with text and segments
- [ ] `embed('hello')` returns `EmbeddingResponse` with float vector
- [ ] `embedBatch()` auto-chunks and returns array of `EmbeddingResponse`
- [ ] `rerank()` returns scored results sorted by relevance
- [ ] Unsupported capability throws `UnsupportedCapabilityException`

### 6. [ ] Agents — Agent class, AnonymousAgent, StructuredAgent, `#[Agent]` attribute, queued agents

#### Agent Class
- `Agent` base class with configurable system prompt, tools, model, provider
- `run(string|array $input): AiResponse` executing agent loop: send -> tool calls -> execute -> re-send -> repeat
- Configurable max iterations (default 10), per-run model/provider override
- Track conversation history within run, return final `AiResponse` with accumulated usage

#### AnonymousAgent
- Inline agent definition without subclassing
- Fluent builder: `AnonymousAgent::create()->systemPrompt('...')->tools([...])->model('...')->provider('...')`
- Closure-based tool definitions for quick prototyping

#### StructuredAgent
- Extends `Agent` with typed output schema (PHP class, `ObjectSchema`, or raw JSON Schema)
- Final response parsed and validated against schema; retry on validation failure

#### `#[Agent]` Attribute
- Define `#[Agent]` with parameters: name, systemPrompt, model, provider, maxIterations
- Apply to PHP classes; discovered at compile time by `AiModule`
- Registered in container, resolvable by name
- Combine with `#[AiTool]` methods on same class (agent's own tools)

#### Queued Agents
- `QueueableAgent` trait for dispatching to queue
- `dispatch(input): PendingAgentJob` serializing agent config for queue transport
- Execute on worker via `lattice/queue`, support job middleware/retries/timeout
- Fire events: `AgentQueued` on dispatch, `AgentCompleted` on worker when done
- Result retrieval via `AgentResult` model or cache key

#### Agent Events
- `AgentStarted` (name, input, provider, model)
- `ToolCalled` (name, tool name, arguments, result, iteration)
- `AgentCompleted` (name, output, usage, iterations, duration)
- `AgentFailed` (name, exception, iteration, partial output)
- `AgentQueued` (name, input, queue name, job ID)
- Fire through `lattice/events` at appropriate lifecycle points

#### Detailed Steps
1. Create `Agents/Agent.php` with run loop, tool execution, iteration tracking, usage accumulation
2. Create `Agents/AnonymousAgent.php` with fluent builder pattern
3. Create `Agents/StructuredAgent.php` with output schema validation and retry
4. Create `Attributes/Agent.php` attribute
5. Create `Agents/QueueableAgent.php` trait with `dispatch()` method and `AgentJob` queue job class
6. Create `Events/AgentStarted.php`, `Events/ToolCalled.php`, `Events/AgentCompleted.php`, `Events/AgentFailed.php`, `Events/AgentQueued.php`
7. Unit tests for agent loop (tool execution, iteration limits, usage), anonymous agent builder, structured agent (typed output, retry), attribute discovery, queued agent (serialization, dispatch, worker execution), events

#### Verification
- [ ] Agent executes multi-step loop: model -> tool -> result -> model -> final response
- [ ] Max iterations prevents infinite loops
- [ ] `AnonymousAgent` builder creates a working agent with inline tools
- [ ] `StructuredAgent` returns validated typed object
- [ ] `#[Agent]` attribute registers agent in container resolvable by name
- [ ] Queued agent dispatches to queue and completes with result retrieval
- [ ] All lifecycle events fire with correct payloads

### 7. [ ] Agent middleware + conversation stores (DatabaseStore, FileStore)

#### Agent Middleware
- Define `AgentMiddleware` interface: `handle(AgentRequest $request, Closure $next): AiResponse`
- `LoggingAgentMiddleware` — log start, each iteration, tool calls, completion
- `RateLimitAgentMiddleware` — rate limit executions per user/key
- `CacheAgentMiddleware` — cache results for identical inputs
- Per-agent and global middleware configuration
- Middleware accesses agent instance, input, iteration count, accumulated state

#### Conversation Stores
- `Store` interface: `get(string $id): ?Conversation`, `save(string $id, Conversation $conversation): void`, `delete(string $id): void`, `list(): array`
- `DatabaseStore` — persist to `ai_conversations` table (id, messages JSON, metadata JSON, created_at, updated_at)
- `FileStore` — persist as JSON files in configurable directory
- `NullStore` — in-memory only, no persistence
- `Conversation` value object — ordered messages with metadata (provider, model, started_at)
- Automatic conversation loading/saving in agents, UUID generation, metadata (tags, user ID, title)

#### AI Request Middleware
- `AiMiddleware` interface: `handle(AiRequest $request, Closure $next): AiResponse`
- `LoggingMiddleware` — log request/response with tokens, latency, cost
- `RateLimitMiddleware` — per-provider RPM/TPM limits via `lattice/rate-limit`
- `CacheMiddleware` — cache identical prompts via `lattice/cache`
- `RetryMiddleware` — exponential backoff on transient failures
- Configurable stack in `config/ai.php`, per-request overrides

#### Detailed Steps
1. Create `Middleware/AgentMiddleware.php` interface and implementations
2. Create `Stores/Store.php` interface, `Stores/DatabaseStore.php`, `Stores/FileStore.php`, `Stores/NullStore.php`
3. Create `Stores/Conversation.php` value object
4. Create migration for `ai_conversations` table
5. Create `Middleware/AiMiddleware.php` interface, `Middleware/LoggingMiddleware.php`, `Middleware/RateLimitMiddleware.php`, `Middleware/CacheMiddleware.php`, `Middleware/RetryMiddleware.php`
6. Unit tests for each middleware, each store implementation, conversation serialization

#### Verification
- [ ] `LoggingAgentMiddleware` logs agent lifecycle events
- [ ] `DatabaseStore` persists and retrieves conversations from database
- [ ] `FileStore` saves and loads conversations as JSON files
- [ ] `CacheMiddleware` returns cached response for identical prompt
- [ ] `RetryMiddleware` retries on transient failures with backoff

### 8. [ ] DX — FakeProvider, testing helpers, `php lattice ai:chat` REPL, docs

#### FakeProvider
- Implement `FakeProvider` implementing `AiProviderInterface`
- Scripted responses: queue `AiResponse` objects returned FIFO, with optional cycling
- Record all calls with full parameters (messages, model, temperature, tools, options)
- Fake streaming (yield chunks from canned response), fake embeddings (deterministic vectors from input hash)
- Fake structured output, fake tool calls, fake images/audio/transcription/reranking
- Support throwing exceptions on specific calls
- Assertion helpers: `assertPromptContains()`, `assertPromptNotContains()`, `assertToolCalled()`, `assertToolNotCalled()`, `assertProviderCalled()`, `assertModelUsed()`, `assertNothingSent()`
- Swap providers with `AiManager::fake()`

#### Prompts System
- `Prompt` base class with `render(array $variables = [])` and `{{ variable }}` interpolation
- Prompt composition (include sub-prompts), loading from `.prompt` files, versioning for A/B testing
- `PromptRegistry` for discovering and resolving by name

#### CLI: `php lattice ai:chat`
- Interactive REPL with `--provider` and `--model` flags
- `--system` flag for system message
- Display streaming responses token-by-token, multi-line input
- Commands: `/clear`, `/switch <provider>`, `/model <model>`, `/system <message>`, `/history`, `/cost`
- Display token usage and estimated cost after each response, color-coded output

#### CLI: `php lattice ai:models` and `php lattice ai:cost`
- `ai:models`: list available models per provider with capabilities, context window, pricing; filter by `--provider`, `--capability`
- `ai:cost`: estimate cost for input text or token count across providers; `--input-tokens`, `--output-tokens`, `--file` flags

#### Token Counting & Cost Tracking
- `AiManager::countTokens()` with provider-specific tokenizers (OpenAI tiktoken), fallback to ~4 chars/token
- `UsageTracker` accumulating usage, per-provider pricing table, custom pricing overrides
- Cost per request in `AiResponse` metadata, `Ai::estimateCost()`, usage reporting
- `AiCallCompleted` event with usage and cost data

#### Documentation
- Getting-started guide, provider setup (API key, model names, capabilities matrix)
- Chat, streaming, tool calling, structured output, agents, images, audio, embeddings, reranking
- Agent middleware, events, stores, prompts, middleware system
- Testing with FakeProvider, CLI commands, cost tracking
- End-to-end examples: chatbot, RAG pipeline, data extraction, content generation, autonomous agent

#### Detailed Steps
1. Create `Testing/FakeProvider.php` with response queue, call recording, all fake operations, assertion methods
2. Add `AiManager::fake()` method that replaces all providers with `FakeProvider` instances
3. Create `Prompts/Prompt.php`, `Prompts/PromptRegistry.php`
4. Create `Commands/AiChatCommand.php`, `Commands/AiModelsCommand.php`, `Commands/AiCostCommand.php`
5. Create `Tracking/UsageTracker.php` and `Tracking/PricingTable.php`
6. Unit tests for FakeProvider (all assertion methods), prompts (rendering, interpolation), CLI commands (argument parsing), token counting, cost calculation

#### Verification
- [ ] `FakeProvider` returns scripted responses and records all calls
- [ ] `assertPromptContains('search')` verifies prompt content in tests
- [ ] `assertToolCalled('getWeather', 2)` verifies tool invocation count
- [ ] `AiManager::fake()` replaces providers globally in test setup
- [ ] `ai:chat` REPL streams responses and tracks conversation
- [ ] `ai:models` lists models with capabilities and pricing
- [ ] `ai:cost` estimates cost for given input across providers
- [ ] Token counting returns reasonable estimates per provider

## Integration Verification
- [ ] Chat completion works: `Ai::chat('What is PHP?')` returns response with text and usage
- [ ] Streaming yields chunks in real-time via generator iteration
- [ ] Tool calling executes: model calls `#[AiTool]` method, receives result, generates final response
- [ ] Structured output returns validated typed PHP object from model response
- [ ] Agent runs autonomously: multi-step reasoning with tools until conclusion
- [ ] FakeProvider enables deterministic testing with zero API calls
- [ ] Provider switching: change `AI_PROVIDER` env variable, same code works with different provider
- [ ] Middleware pipeline: logging, rate limiting, caching all execute in configured order
