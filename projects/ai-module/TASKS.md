# lattice/ai -- Task List


## Phase 1: Core

### AiManager (Provider Registry, Default Provider, Facade)
- [ ] Implement `AiManager` as the primary entry point for all AI operations
- [ ] Register providers by name in an internal registry (`string $name => AiProviderInterface`)
- [ ] Resolve the default provider from config (`ai.default`)
- [ ] Implement `provider(string $name): AiProviderInterface` to retrieve a specific provider
- [ ] Implement `getDefaultProvider(): AiProviderInterface`
- [ ] Delegate top-level methods (`chat`, `stream`, `structured`, `embed`, `image`, `audio`, `transcribe`, `rerank`) to the default provider
- [ ] Support dynamic provider switching at runtime via `using(string $provider)`
- [ ] Throw `ProviderNotFoundException` for unregistered provider names
- [ ] Unit tests for registry, default resolution, delegation, and error cases

### AiProviderInterface (Chat, Stream, Structured, Tools, Embeddings, Images, Audio, Transcribe, Rerank)
- [ ] Define `AiProviderInterface` with methods: `chat()`, `stream()`, `structured()`, `embed()`, `embedBatch()`, `generateImage()`, `synthesize()`, `transcribe()`, `rerank()`
- [ ] Define method signatures with typed parameters and return types
- [ ] Support passing model, temperature, maxTokens, topP, stopSequences, tools per call
- [ ] Support system message/instruction configuration
- [ ] Define `ProviderCapability` enum: `Chat`, `Streaming`, `ToolCalling`, `StructuredOutput`, `Embeddings`, `ImageGeneration`, `AudioSynthesis`, `AudioTranscription`, `Reranking`, `Vision`, `FileHandling`
- [ ] Add `capabilities(): array<ProviderCapability>` method so consumers can check provider support at runtime
- [ ] Add `supports(ProviderCapability $capability): bool` convenience method
- [ ] Throw `UnsupportedCapabilityException` with provider name and capability when an unsupported method is called
- [ ] Unit tests for interface contract validation

### Message Value Objects
- [ ] Implement `UserMessage` -- immutable value object for user input (text, optional images/files)
- [ ] Implement `AssistantMessage` -- immutable value object for model output (text, optional tool calls)
- [ ] Implement `SystemMessage` -- immutable value object for system instructions
- [ ] Implement `ToolCall` -- immutable value object (id, tool name, arguments as array)
- [ ] Implement `ToolResult` -- immutable value object (tool call id, result data, is error flag)
- [ ] Support multipart content on `UserMessage` (text + images for vision-capable models)
- [ ] Support file attachments on `UserMessage` (uploaded file references)
- [ ] Factory methods: `UserMessage::create()`, `SystemMessage::create()`, `AssistantMessage::create()`, `AssistantMessage::withToolCalls()`
- [ ] Implement `MessageCollection` for managing ordered message lists
- [ ] JSON serialization and deserialization on all message types
- [ ] Unit tests for all message types, factory methods, and serialization round-trip

### Response Value Objects
- [ ] Implement `AiResponse` -- immutable value object with text content, usage, finish reason, tool calls, model ID, raw provider response
- [ ] Implement `StreamChunk` -- immutable value object (delta text, accumulated text, finish reason, partial usage, tool call deltas)
- [ ] Implement `Usage` -- immutable value object (prompt tokens, completion tokens, total tokens)
- [ ] Implement `FinishReason` enum: `Stop`, `Length`, `ToolCall`, `ContentFilter`, `Error`
- [ ] Implement `EmbeddingResponse` -- immutable value object (vector as float array, model, usage)
- [ ] Implement `ImageResponse` -- immutable value object (URL or base64, revised prompt, model)
- [ ] Implement `AudioResponse` -- immutable value object (audio data, format, duration)
- [ ] Implement `TranscriptionResponse` -- immutable value object (text, segments, language, duration)
- [ ] Implement `RerankResponse` -- immutable value object (scored results as array of index + relevance score)
- [ ] Helper methods on `AiResponse`: `hasToolCalls()`, `getToolCalls()`, `isComplete()`, `getText()`, `getUsage()`
- [ ] Unit tests for all response types, construction, and helper methods

### Configuration
- [ ] Define `AiConfig` value object with typed properties for all settings
- [ ] Support default provider selection (`ai.default`)
- [ ] Support per-provider configuration: API key, base URL, default model, organization ID, API version, timeout
- [ ] Support global defaults: temperature, max tokens, top-p, stop sequences
- [ ] Support per-provider model defaults (different default model per provider)
- [ ] Support middleware stack configuration (`ai.middleware`)
- [ ] Support store configuration (`ai.store`)
- [ ] Load configuration from `config/ai.php` config file
- [ ] Validate configuration at boot time (missing API keys, invalid provider names, invalid middleware classes)
- [ ] Mask API keys in debug output and error messages
- [ ] Unit tests for config loading, defaults, validation, and key masking

### AiServiceProvider and AiModule
- [ ] Create `AiServiceProvider` that registers all AI services
- [ ] Register `AiConfig` from `config/ai.php`
- [ ] Register `AiManager` as singleton
- [ ] Register all 15 provider implementations in the container
- [ ] Register provider factory closures (lazy instantiation -- providers created only when first used)
- [ ] Register default provider binding based on config
- [ ] Register middleware pipeline from config
- [ ] Register store implementation from config
- [ ] Publish default `config/ai.php` configuration file
- [ ] Create `AiModule` with `#[Module]` attribute
- [ ] Register CLI commands via module (`ai:chat`, `ai:models`, `ai:cost`)
- [ ] Trigger compile-time discovery of `#[AiTool]` and `#[Agent]` attributes
- [ ] Unit tests for service provider registration, lazy instantiation, and provider resolution

### `#[AiProvider]` Attribute for DI Injection
- [ ] Define `#[AiProvider]` PHP attribute with optional provider name parameter
- [ ] Support applying `#[AiProvider('anthropic')]` to constructor parameters
- [ ] Support applying `#[AiProvider('openai')]` to typed properties
- [ ] Container resolves the correct provider implementation based on attribute value
- [ ] Support `#[AiProvider]` without argument to inject the default provider
- [ ] Integrate with LatticePHP's compile-time attribute resolver
- [ ] Unit tests for attribute-based injection with different provider names and default fallback


## Phase 2: Providers (15)

### AnthropicProvider (Claude)
- [ ] Implement `AnthropicProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Anthropic Messages API (`POST /v1/messages`)
- [ ] Implement streaming via Messages API with SSE parsing (`stream: true`)
- [ ] Implement tool/function calling with tool schemas (Anthropic tool_use format)
- [ ] Implement structured output via JSON mode / assistant prefill technique
- [ ] Implement vision support (images in user messages via base64 or URL)
- [ ] Handle API error responses (rate limit 429, overloaded 529, invalid request 400, auth 401)
- [ ] Map Anthropic stop reasons (`end_turn`, `max_tokens`, `tool_use`, `stop_sequence`) to `FinishReason` enum
- [ ] Support model selection (claude-opus, claude-sonnet, claude-haiku)
- [ ] Support API version header (`anthropic-version`)
- [ ] Support extended thinking / chain-of-thought when available
- [ ] Unit tests with mock HTTP responses for all operations

### OpenAiProvider (GPT)
- [ ] Implement `OpenAiProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Chat Completions API (`POST /v1/chat/completions`)
- [ ] Implement streaming via Chat Completions API with SSE parsing (`stream: true`)
- [ ] Implement tool/function calling with function schemas (OpenAI function/tool format)
- [ ] Implement structured output via JSON mode (`response_format: { type: "json_object" }`) and JSON schema mode
- [ ] Implement embeddings via Embeddings API (`POST /v1/embeddings`) -- single and batch
- [ ] Implement image generation via Images API (`POST /v1/images/generations`) -- generate, edit, variations
- [ ] Implement audio synthesis via Audio Speech API (`POST /v1/audio/speech`) -- text-to-speech
- [ ] Implement audio transcription via Audio Transcriptions API (`POST /v1/audio/transcriptions`) -- speech-to-text (Whisper)
- [ ] Support organization ID header
- [ ] Handle API error responses (rate limit, invalid request, context length exceeded, content policy)
- [ ] Map OpenAI finish reasons (`stop`, `length`, `tool_calls`, `content_filter`) to `FinishReason` enum
- [ ] Support model selection (gpt-4o, gpt-4o-mini, gpt-4-turbo, o1, o3, etc.)
- [ ] Unit tests with mock HTTP responses for all operations

### GeminiProvider (Google)
- [ ] Implement `GeminiProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Generate Content API (`POST /v1/models/{model}:generateContent`)
- [ ] Implement streaming via Generate Content API with SSE (`POST /v1/models/{model}:streamGenerateContent`)
- [ ] Implement tool/function calling with function declarations (Gemini tool format)
- [ ] Implement structured output via JSON mode / response schema
- [ ] Implement vision support (images in content parts)
- [ ] Implement embeddings via Embedding API (`POST /v1/models/{model}:embedContent`)
- [ ] Handle API error responses (rate limit, invalid request, safety filters)
- [ ] Map Gemini finish reasons (`STOP`, `MAX_TOKENS`, `SAFETY`, `RECITATION`) to `FinishReason` enum
- [ ] Support model selection (gemini-2.0-flash, gemini-2.0-pro, gemini-1.5-pro, gemini-1.5-flash)
- [ ] Support API key authentication and OAuth
- [ ] Unit tests with mock HTTP responses for all operations

### AzureOpenAiProvider
- [ ] Implement `AzureOpenAiProvider` extending/wrapping `OpenAiProvider`
- [ ] Support Azure-specific endpoint format (`https://{resource}.openai.azure.com/openai/deployments/{deployment}`)
- [ ] Support Azure API key authentication and Azure AD token authentication
- [ ] Support API version query parameter (`api-version`)
- [ ] Map deployment names to model identifiers
- [ ] Inherit all OpenAI capabilities: chat, streaming, tools, structured output, embeddings, images
- [ ] Unit tests for Azure-specific URL construction, auth, and version handling

### DeepSeekProvider
- [ ] Implement `DeepSeekProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via DeepSeek Chat API (OpenAI-compatible endpoint)
- [ ] Implement streaming with SSE parsing
- [ ] Implement tool/function calling
- [ ] Implement structured output via JSON mode
- [ ] Support model selection (deepseek-chat, deepseek-reasoner)
- [ ] Handle DeepSeek-specific error responses
- [ ] Unit tests with mock HTTP responses

### GroqProvider
- [ ] Implement `GroqProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Groq API (OpenAI-compatible endpoint)
- [ ] Implement streaming with SSE parsing
- [ ] Implement tool/function calling
- [ ] Implement structured output via JSON mode
- [ ] Support model selection (llama, mixtral, gemma models on Groq)
- [ ] Handle Groq-specific rate limits and error responses
- [ ] Unit tests with mock HTTP responses

### MistralProvider
- [ ] Implement `MistralProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Mistral Chat API
- [ ] Implement streaming with SSE parsing
- [ ] Implement tool/function calling
- [ ] Implement structured output via JSON mode
- [ ] Implement embeddings via Mistral Embeddings API
- [ ] Support model selection (mistral-large, mistral-medium, mistral-small, codestral, etc.)
- [ ] Handle Mistral-specific error responses
- [ ] Unit tests with mock HTTP responses

### OllamaProvider (Local Models)
- [ ] Implement `OllamaProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Ollama REST API (`POST /api/chat`)
- [ ] Implement streaming via Ollama streaming response (newline-delimited JSON)
- [ ] Implement tool/function calling where supported by the local model
- [ ] Implement structured output via JSON format instruction
- [ ] Implement embeddings via Ollama Embeddings API (`POST /api/embeddings`)
- [ ] Support configurable base URL (default `http://localhost:11434`)
- [ ] Support model selection from locally available models
- [ ] Support model pull status checking
- [ ] Unit tests with mock HTTP responses

### OpenRouterProvider (Multi-Model Gateway)
- [ ] Implement `OpenRouterProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via OpenRouter API (OpenAI-compatible endpoint)
- [ ] Implement streaming with SSE parsing
- [ ] Implement tool/function calling (provider-dependent)
- [ ] Implement structured output (provider-dependent)
- [ ] Support model selection from OpenRouter's full model catalog (100+ models)
- [ ] Support route preferences (cheapest, fastest, specific provider)
- [ ] Support fallback models
- [ ] Pass HTTP referer and app name headers as required by OpenRouter
- [ ] Unit tests with mock HTTP responses

### CohereProvider
- [ ] Implement `CohereProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via Cohere Chat API (`POST /v2/chat`)
- [ ] Implement streaming with SSE parsing
- [ ] Implement tool/function calling
- [ ] Implement embeddings via Cohere Embed API (`POST /v2/embed`) -- single and batch
- [ ] Implement reranking via Cohere Rerank API (`POST /v2/rerank`)
- [ ] Support model selection (command-r-plus, command-r, embed-v4, rerank-v3.5)
- [ ] Support input type specification for embeddings (search_document, search_query, classification, clustering)
- [ ] Unit tests with mock HTTP responses

### XaiProvider (Grok)
- [ ] Implement `XaiProvider` implementing `AiProviderInterface`
- [ ] Implement chat completion via xAI API (OpenAI-compatible endpoint)
- [ ] Implement streaming with SSE parsing
- [ ] Implement tool/function calling
- [ ] Implement structured output via JSON mode
- [ ] Support model selection (grok-2, grok-3, etc.)
- [ ] Handle xAI-specific error responses
- [ ] Unit tests with mock HTTP responses

### ElevenLabsProvider (Audio Synthesis)
- [ ] Implement `ElevenLabsProvider` implementing `AiProviderInterface`
- [ ] Implement audio synthesis via ElevenLabs Text-to-Speech API (`POST /v1/text-to-speech/{voice_id}`)
- [ ] Support voice selection from available voices
- [ ] Support voice settings (stability, similarity boost, style, speaker boost)
- [ ] Support multiple output formats (mp3, wav, ogg, flac)
- [ ] Support streaming audio output
- [ ] Throw `UnsupportedCapabilityException` for chat, embeddings, and other non-audio operations
- [ ] Unit tests with mock HTTP responses

### JinaProvider (Embeddings, Reranking)
- [ ] Implement `JinaProvider` implementing `AiProviderInterface`
- [ ] Implement embeddings via Jina Embeddings API -- single and batch
- [ ] Implement reranking via Jina Reranker API
- [ ] Support model selection (jina-embeddings-v3, jina-reranker-v2, etc.)
- [ ] Support dimensionality configuration for embeddings
- [ ] Support late interaction embeddings (ColBERT)
- [ ] Throw `UnsupportedCapabilityException` for chat and other unsupported operations
- [ ] Unit tests with mock HTTP responses

### VoyageAiProvider (Embeddings)
- [ ] Implement `VoyageAiProvider` implementing `AiProviderInterface`
- [ ] Implement embeddings via VoyageAI Embeddings API -- single and batch
- [ ] Support model selection (voyage-3, voyage-3-lite, voyage-code-3, etc.)
- [ ] Support input type specification (query, document)
- [ ] Support dimensionality truncation
- [ ] Throw `UnsupportedCapabilityException` for chat and other unsupported operations
- [ ] Unit tests with mock HTTP responses

### Provider Base Class (Shared HTTP, Retry, Timeout)
- [ ] Implement `AbstractProvider` base class with shared functionality
- [ ] Shared HTTP client configuration via `lattice/http-client` (base URL, headers, auth)
- [ ] Shared timeout handling (connect timeout, request timeout, configurable per provider)
- [ ] Shared retry logic with exponential backoff and jitter (configurable max retries, base delay, max delay)
- [ ] Respect `Retry-After` header when present
- [ ] Do not retry on client errors (400, 401, 403) -- only transient failures (429, 500, 502, 503, 529)
- [ ] Shared SSE stream parser for providers using Server-Sent Events
- [ ] Shared error response normalization (map provider-specific errors to `AiException` subclasses)
- [ ] Shared request/response logging hooks (delegate to middleware pipeline)
- [ ] Unit tests for retry logic, timeout handling, SSE parsing, and error normalization


## Phase 3: Features

### Chat Completion (Single Message, Conversation)
- [ ] Implement `AiManager::chat(array $messages, array $options = []): AiResponse`
- [ ] Support single user message shorthand: `chat('What is PHP?')`
- [ ] Support full conversation with message objects: `chat([SystemMessage::create('...'), UserMessage::create('...')])`
- [ ] Support options: model, temperature, maxTokens, topP, stopSequences, tools, responseFormat
- [ ] Support system message as a separate parameter for convenience
- [ ] Return `AiResponse` with content, usage, finish reason, model, and optional tool calls
- [ ] Delegate to the active provider's implementation
- [ ] Integration tests verifying delegation and response mapping per provider

### Streaming (Yield Chunks, onToken Callback, Async Iteration)
- [ ] Implement `AiManager::stream(array $messages, array $options = []): Generator<StreamChunk>`
- [ ] Return a generator that yields `StreamChunk` objects as tokens arrive
- [ ] Each `StreamChunk` includes: delta text, accumulated text so far, finish reason (on last chunk), partial usage
- [ ] Support `onToken` callback: `stream($messages, onToken: fn(StreamChunk $chunk) => ...)`
- [ ] Support streaming tool calls (accumulate partial JSON across chunks, emit complete `ToolCall` on finish)
- [ ] Handle stream interruption gracefully (return partial response with metadata)
- [ ] Normalize SSE parsing differences across providers (OpenAI, Anthropic, Gemini each have different SSE formats)
- [ ] Support `onComplete` callback invoked when the stream finishes
- [ ] Unit tests verifying chunk ordering, completeness, callback invocation, and tool call accumulation

### Tool / Function Calling
- [ ] Define `ToolDefinition` class (name, description, parameters as JSON Schema)
- [ ] Support passing `ToolDefinition` array to chat/stream calls via `tools` option
- [ ] Parse tool call responses from each provider's format into normalized `ToolCall` objects
- [ ] Implement tool result passing back to the model (append `ToolResult` message and re-call)
- [ ] Support multi-step tool use loops: model calls tool -> gets result -> calls another tool or responds -> repeat
- [ ] Configure maximum tool loop iterations (default 10, configurable)
- [ ] Validate tool call arguments against the tool's JSON Schema before execution
- [ ] Support parallel tool calls (multiple tool calls in a single response)
- [ ] Unit tests for tool calling round-trip, multi-step loops, validation, and parallel calls

### `#[AiTool]` Attribute for Tool Definition with Auto-Schema
- [ ] Define `#[AiTool]` attribute with parameters: name (optional, defaults to method name), description
- [ ] Support applying `#[AiTool]` to PHP class methods
- [ ] Auto-extract tool parameters from method signature (name, type, nullable, default value)
- [ ] Auto-generate JSON Schema for parameters from PHP types (string, int, float, bool, array, enum)
- [ ] Extract parameter descriptions from `@param` docblock tags
- [ ] Support `#[AiToolParam]` attribute on parameters for description, constraint, and enum overrides
- [ ] Support complex parameter types (nested objects, arrays of typed items)
- [ ] Register `#[AiTool]` methods in a tool registry at compile time via `AiModule`
- [ ] Support tool classes (class with `__invoke` method annotated with `#[AiTool]`)
- [ ] Unit tests for attribute discovery, schema generation, parameter extraction, and invocation

### Structured Output (JSON Mode, Schema Validation, ObjectSchema)
- [ ] Implement `AiManager::structured(array $messages, string|array $schema, array $options = []): mixed`
- [ ] Accept a PHP class name as schema -- auto-generate JSON Schema from class properties
- [ ] Accept a `ObjectSchema` fluent builder for complex schemas
- [ ] Accept raw JSON Schema array for maximum flexibility
- [ ] Use provider-native JSON mode where available (OpenAI `json_object`/`json_schema`, Gemini response schema)
- [ ] Use assistant prefill technique for Anthropic Claude structured output
- [ ] Deserialize JSON response into the target PHP class using hydrator
- [ ] Validate response against schema before deserialization
- [ ] Retry on malformed JSON (configurable max retries, default 2)
- [ ] Include the schema instruction in the system prompt for providers without native JSON mode
- [ ] Unit tests for structured output with classes, ObjectSchema, raw schema, validation, and retry

### Image Generation (Generate, Edit, Variations)
- [ ] Implement `AiManager::generateImage(string $prompt, array $options = []): ImageResponse`
- [ ] Support options: size, quality, style, number of images, response format (URL or base64)
- [ ] Implement image editing: `editImage(string $prompt, string|resource $image, array $options = []): ImageResponse`
- [ ] Implement image variations: `imageVariation(string|resource $image, array $options = []): ImageResponse`
- [ ] Support OpenAI DALL-E models and Azure OpenAI image generation
- [ ] Throw `UnsupportedCapabilityException` for providers without image support
- [ ] Unit tests with mock HTTP responses for generate, edit, and variation operations

### Audio Synthesis (Text-to-Speech)
- [ ] Implement `AiManager::synthesize(string $text, array $options = []): AudioResponse`
- [ ] Support options: voice, model, output format (mp3, wav, opus, flac), speed
- [ ] Support streaming audio output for long texts
- [ ] Support OpenAI TTS and ElevenLabs providers
- [ ] Return `AudioResponse` with audio binary data, format, and duration
- [ ] Throw `UnsupportedCapabilityException` for providers without audio synthesis
- [ ] Unit tests with mock HTTP responses

### Audio Transcription (Speech-to-Text)
- [ ] Implement `AiManager::transcribe(string|resource $audio, array $options = []): TranscriptionResponse`
- [ ] Support options: model, language hint, response format (text, json, verbose_json, srt, vtt), temperature
- [ ] Support file upload from path or stream resource
- [ ] Return `TranscriptionResponse` with text, segments (timestamps), detected language, duration
- [ ] Support OpenAI Whisper
- [ ] Throw `UnsupportedCapabilityException` for providers without transcription
- [ ] Unit tests with mock HTTP responses

### Embeddings (Single, Batch, Dimensions)
- [ ] Implement `AiManager::embed(string $text, array $options = []): EmbeddingResponse`
- [ ] Implement `AiManager::embedBatch(array $texts, array $options = []): array<EmbeddingResponse>`
- [ ] Support options: model, dimensions (where provider supports dimensionality reduction)
- [ ] Support input type (query vs. document) for providers that differentiate (Cohere, VoyageAI)
- [ ] Return `EmbeddingResponse` with float array vector, model, and usage
- [ ] Handle batch size limits per provider (auto-chunk large batches if needed)
- [ ] Supported providers: OpenAI, Gemini, Mistral, Ollama, Cohere, Jina, VoyageAI
- [ ] Throw `UnsupportedCapabilityException` for providers without embedding support
- [ ] Unit tests for single embedding, batch embedding, dimensionality, and input types

### Reranking (Reorder Results by Relevance)
- [ ] Implement `AiManager::rerank(string $query, array $documents, array $options = []): RerankResponse`
- [ ] Support options: model, top N results, return documents flag
- [ ] Accept documents as array of strings or array of objects with text field
- [ ] Return `RerankResponse` with scored results (original index, relevance score, optional document)
- [ ] Supported providers: Cohere, Jina
- [ ] Throw `UnsupportedCapabilityException` for providers without reranking
- [ ] Unit tests with mock HTTP responses

### File Handling (Upload, Reference in Prompts)
- [ ] Implement `AiManager::uploadFile(string $path, array $options = []): FileReference`
- [ ] Support uploading files for use in prompts (PDFs, images, documents)
- [ ] Return `FileReference` value object with provider-specific file ID
- [ ] Support referencing uploaded files in `UserMessage` content
- [ ] Support inline base64 encoding as fallback for providers without file upload APIs
- [ ] Support MIME type detection and validation
- [ ] Clean up uploaded files when no longer needed
- [ ] Unit tests for file upload, reference, and cleanup


## Phase 4: Agents

### Agent Class (System Prompt, Tools, Model, Provider)
- [ ] Implement `Agent` base class with configurable system prompt, tools, model, and provider
- [ ] Implement `run(string|array $input): AiResponse` method that executes the agent loop
- [ ] Agent loop: send messages -> if tool calls, execute tools, append results, re-send -> repeat until no tool calls or max iterations
- [ ] Support configurable max iterations (default 10) to prevent infinite loops
- [ ] Support configurable tools as `ToolDefinition` array or `#[AiTool]` class references
- [ ] Support overriding model and provider per run
- [ ] Track conversation history within a run
- [ ] Return final `AiResponse` with accumulated usage across all iterations
- [ ] Unit tests for agent loop, tool execution, iteration limits, and usage accumulation

### AnonymousAgent (Inline Agent Definition)
- [ ] Implement `AnonymousAgent` for defining agents inline without subclassing
- [ ] Fluent builder: `AnonymousAgent::create()->systemPrompt('...')->tools([...])->model('...')->provider('...')`
- [ ] Support closure-based tool definitions for quick prototyping
- [ ] Support all `Agent` base class features
- [ ] Unit tests for builder pattern and inline tool definitions

### StructuredAgent (Returns Typed Objects)
- [ ] Implement `StructuredAgent` extending `Agent` with typed output
- [ ] Accept output schema as PHP class name, `ObjectSchema`, or raw JSON Schema
- [ ] Final agent response is parsed and validated against the schema
- [ ] Return typed object instead of raw `AiResponse`
- [ ] Retry final response if schema validation fails (configurable max retries)
- [ ] Unit tests for typed output, schema validation, and retry

### Agent Middleware (Before/After Hooks, Logging, Rate Limiting, Caching)
- [ ] Define `AgentMiddleware` interface with `handle(AgentRequest $request, Closure $next): AiResponse`
- [ ] Implement `LoggingAgentMiddleware` -- log agent start, each iteration, tool calls, and completion
- [ ] Implement `RateLimitAgentMiddleware` -- rate limit agent executions per user/key
- [ ] Implement `CacheAgentMiddleware` -- cache agent results for identical inputs
- [ ] Support per-agent middleware configuration
- [ ] Support global agent middleware in config
- [ ] Middleware has access to agent instance, input, iteration count, and accumulated state
- [ ] Unit tests for each middleware and the pipeline

### Queued Agents (Dispatch to Queue for Long-Running AI Tasks)
- [ ] Implement `QueueableAgent` trait for agents that can be dispatched to the queue
- [ ] Implement `dispatch(string|array $input): PendingAgentJob` to queue an agent run
- [ ] Serialize agent configuration (system prompt, tools, model, provider, input) for queue transport
- [ ] Deserialize and execute on the worker via `lattice/queue`
- [ ] Support job middleware, retries, and timeout configuration from `lattice/queue`
- [ ] Fire `AgentQueued` event on dispatch
- [ ] Fire `AgentCompleted` event on worker when done (with result)
- [ ] Support result retrieval via `AgentResult` model or cache key
- [ ] Unit tests for serialization, dispatch, worker execution, and result retrieval

### Agent Conversation History (Store Interface, DatabaseStore, FileStore)
- [ ] Define `Store` interface: `get(string $id): ?Conversation`, `save(string $id, Conversation $conversation): void`, `delete(string $id): void`, `list(): array`
- [ ] Implement `DatabaseStore` -- persist conversations to a database table via `lattice/database`
- [ ] Database schema: `ai_conversations` table with id, messages (JSON), metadata (JSON), created_at, updated_at
- [ ] Implement `FileStore` -- persist conversations as JSON files in a configurable directory
- [ ] Implement `NullStore` -- no persistence, in-memory only
- [ ] Implement `Conversation` value object -- ordered list of messages with metadata (provider, model, started_at)
- [ ] Support automatic conversation loading/saving in agents via store
- [ ] Support conversation ID generation (UUID)
- [ ] Support conversation metadata (tags, user ID, title)
- [ ] Unit tests for each store implementation, conversation serialization, and metadata

### `#[Agent]` Attribute for Declarative Agent Definition
- [ ] Define `#[Agent]` attribute with parameters: name, systemPrompt, model, provider, maxIterations
- [ ] Support applying `#[Agent]` to PHP classes
- [ ] Attributed agent classes are discovered at compile time by `AiModule`
- [ ] Attributed agents are registered in the container and resolvable by name
- [ ] Support combining `#[Agent]` with `#[AiTool]` methods on the same class (agent's own tools)
- [ ] Unit tests for attribute discovery, registration, and resolution

### Agent Events
- [ ] Define `AgentStarted` event (agent name, input, provider, model)
- [ ] Define `ToolCalled` event (agent name, tool name, arguments, result, iteration)
- [ ] Define `AgentCompleted` event (agent name, output, total usage, total iterations, duration)
- [ ] Define `AgentFailed` event (agent name, exception, iteration, partial output)
- [ ] Define `AgentQueued` event (agent name, input, queue name, job ID)
- [ ] Fire events through `lattice/events` dispatcher at appropriate points in the agent lifecycle
- [ ] Unit tests verifying events fire at correct lifecycle points with correct payloads


## Phase 5: DX & Testing

### FakeProvider (Scripted Responses, Assertion Helpers)
- [ ] Implement `FakeProvider` implementing `AiProviderInterface`
- [ ] Support scripted responses: queue `AiResponse` objects returned in FIFO order
- [ ] Support response sequences: different response per call, with optional cycling when exhausted
- [ ] Record all calls with full parameters (messages, model, temperature, tools, options)
- [ ] Support fake streaming (return `StreamChunk` generator from canned response text)
- [ ] Support fake embeddings (return deterministic vectors based on input hash)
- [ ] Support fake structured output (return pre-built objects matching requested schema)
- [ ] Support fake tool calls (return scripted tool call responses)
- [ ] Support fake images, audio, transcription, and reranking
- [ ] Support throwing exceptions on specific calls for error path testing
- [ ] Implement `assertPromptContains(string $text)` -- verify any recorded prompt included the text
- [ ] Implement `assertPromptNotContains(string $text)` -- verify no recorded prompt included the text
- [ ] Implement `assertToolCalled(string $toolName, ?int $times = null)` -- verify a tool was invoked (optionally N times)
- [ ] Implement `assertToolNotCalled(string $toolName)` -- verify a tool was never invoked
- [ ] Implement `assertProviderCalled(?int $times = null)` -- verify number of AI calls made
- [ ] Implement `assertModelUsed(string $model)` -- verify a specific model was used
- [ ] Implement `assertNothingSent()` -- verify no calls were made
- [ ] Support swapping real providers with `FakeProvider` in test setup via `AiManager::fake()`
- [ ] Unit tests for the fake provider and every assertion method

### Prompts System (Reusable Prompt Templates)
- [ ] Implement `Prompt` base class with `render(array $variables = []): string` method
- [ ] Support variable interpolation with `{{ variable }}` syntax
- [ ] Support prompt composition (include sub-prompts / fragments)
- [ ] Support loading prompts from `.prompt` files in a configurable directory
- [ ] Support prompt versioning (different versions of the same prompt for A/B testing)
- [ ] Implement `PromptRegistry` for discovering and resolving prompts by name
- [ ] Unit tests for rendering, interpolation, composition, and file loading

### CLI: `php lattice ai:chat` (Interactive REPL)
- [ ] Implement `ai:chat` CLI command for interactive conversation in the terminal
- [ ] Support provider and model selection via flags: `--provider=anthropic --model=claude-sonnet-4-20250514`
- [ ] Support system message via flag: `--system="You are a helpful assistant"`
- [ ] Display streaming responses token-by-token in the terminal
- [ ] Support multi-line input (end with empty line or Ctrl+D)
- [ ] Support `/clear` to reset conversation history
- [ ] Support `/switch <provider>` to change provider mid-conversation
- [ ] Support `/model <model>` to change model mid-conversation
- [ ] Support `/system <message>` to update the system message
- [ ] Support `/history` to display the conversation so far
- [ ] Support `/cost` to display accumulated cost for the session
- [ ] Display token usage and estimated cost after each response
- [ ] Color-coded output (user input, assistant response, system info, errors)
- [ ] Unit tests for command argument parsing and input handling

### CLI: `php lattice ai:models` (List Available Models)
- [ ] Implement `ai:models` CLI command
- [ ] List all available models per configured provider
- [ ] Display model name, capabilities, context window size, and pricing where known
- [ ] Support filtering by provider: `--provider=openai`
- [ ] Support filtering by capability: `--capability=embeddings`
- [ ] Tabular output format
- [ ] Unit tests for command output formatting

### CLI: `php lattice ai:cost` (Estimate Cost)
- [ ] Implement `ai:cost` CLI command
- [ ] Accept input text or token count and estimate cost across providers/models
- [ ] Display comparison table: provider, model, input cost, output cost (estimated), total
- [ ] Support `--input-tokens` and `--output-tokens` flags for direct token count input
- [ ] Support `--file` flag to estimate cost for a file's content
- [ ] Unit tests for cost calculation and output formatting

### Token Counting Per Provider
- [ ] Implement `AiManager::countTokens(string|array $messages, array $options = []): int`
- [ ] Support provider-specific tokenizers where available (OpenAI tiktoken)
- [ ] Fall back to approximate counting (~4 chars/token heuristic) where no tokenizer exists
- [ ] Support counting tokens for specific models (different tokenizers per model family)
- [ ] Use token counts for context window management, truncation decisions, and cost estimation
- [ ] Unit tests for token counting accuracy per provider

### Cost Tracking and Usage Logging
- [ ] Implement `UsageTracker` service that accumulates usage across requests
- [ ] Maintain per-provider pricing table for all supported models (input price, output price per 1M tokens)
- [ ] Support custom pricing overrides in config
- [ ] Calculate cost per request and include in `AiResponse` metadata
- [ ] Implement `Ai::estimateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float`
- [ ] Support usage reporting: total tokens, total cost, breakdown by provider/model over a time period
- [ ] Emit `AiCallCompleted` event with usage and cost data for custom logging/alerting
- [ ] Unit tests for cost calculation accuracy and usage accumulation

### AI Request Middleware System
- [ ] Define `AiMiddleware` interface: `handle(AiRequest $request, Closure $next): AiResponse`
- [ ] Implement `AiRequest` value object (messages, options, provider name, model)
- [ ] Implement middleware pipeline that wraps provider calls
- [ ] Implement `LoggingMiddleware` -- log every request/response with tokens, latency, cost
- [ ] Implement `RateLimitMiddleware` -- per-provider RPM/TPM limits via `lattice/rate-limit`
- [ ] Implement `CacheMiddleware` -- cache identical prompts via `lattice/cache` (temperature=0 by default, configurable)
- [ ] Implement `RetryMiddleware` -- exponential backoff with jitter on transient failures (429, 500, 502, 503, 529)
- [ ] Support configurable middleware stack in `config/ai.php`
- [ ] Support per-request middleware overrides
- [ ] Unit tests for each middleware and the pipeline execution order

### Documentation with Examples Per Provider and Feature
- [ ] Write getting-started guide: installation, configuration, first chat call
- [ ] Document each provider's setup: API key acquisition, model names, capabilities matrix
- [ ] Document chat completion with examples (simple string, full conversation, with options)
- [ ] Document streaming with generator iteration and callback examples
- [ ] Document tool/function calling with `#[AiTool]` attribute examples and manual `ToolDefinition`
- [ ] Document structured output with PHP class targets, `ObjectSchema`, and raw schema
- [ ] Document agent system: Agent class, AnonymousAgent, StructuredAgent, queued agents
- [ ] Document agent middleware and events
- [ ] Document image generation, audio synthesis, and audio transcription
- [ ] Document embeddings and reranking
- [ ] Document file handling
- [ ] Document prompts system
- [ ] Document middleware system (built-in and custom)
- [ ] Document store system (DatabaseStore, FileStore)
- [ ] Document testing with `FakeProvider` and assertion helpers
- [ ] Document CLI commands (ai:chat, ai:models, ai:cost)
- [ ] Document cost tracking and usage logging
- [ ] Add end-to-end code examples: chatbot, RAG pipeline, data extraction, content generation, autonomous agent
