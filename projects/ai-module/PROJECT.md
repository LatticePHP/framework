# lattice/ai -- LatticePHP AI SDK

**Package:** `lattice/ai`

## Overview

A comprehensive, multi-provider AI SDK for LatticePHP -- the framework-native way to integrate AI into PHP applications. `lattice/ai` provides a unified API across 15+ AI providers for chat completion, streaming, tool calling, structured output, autonomous agents, image generation, audio synthesis and transcription, embeddings, and reranking.

Built on LatticePHP's attribute-driven dependency injection system, `lattice/ai` makes provider swapping a config-only change. Inject `#[AiProvider('anthropic')]` on any constructor parameter and the container resolves the correct provider automatically. Define tools with `#[AiTool]`, agents with `#[Agent]`, and let the framework wire everything together.

## Supported Providers (15)

| Provider | Service | Capabilities |
|---|---|---|
| **Anthropic** | Claude (Messages API) | Chat, streaming, tools, structured output, vision |
| **OpenAI** | GPT (Chat Completions API) | Chat, streaming, tools, structured output, images, audio, transcription, embeddings |
| **Gemini** | Google (generateContent API) | Chat, streaming, tools, structured output, vision, embeddings |
| **Azure OpenAI** | Azure-hosted OpenAI models | Chat, streaming, tools, structured output, images, embeddings |
| **DeepSeek** | DeepSeek (Chat API) | Chat, streaming, tools, structured output |
| **Groq** | Groq (fast inference) | Chat, streaming, tools, structured output |
| **Mistral** | Mistral AI (Chat API) | Chat, streaming, tools, structured output, embeddings |
| **Ollama** | Local models (REST API) | Chat, streaming, tools, structured output, embeddings |
| **OpenRouter** | Multi-model gateway | Chat, streaming, tools, structured output (routes to 100+ models) |
| **Cohere** | Cohere (Chat + Embed API) | Chat, streaming, tools, embeddings, reranking |
| **xAI** | Grok (Chat API) | Chat, streaming, tools, structured output |
| **ElevenLabs** | ElevenLabs (Speech API) | Audio synthesis (text-to-speech) |
| **Jina** | Jina AI (Embed + Rerank API) | Embeddings, reranking |
| **VoyageAI** | Voyage (Embed API) | Embeddings |
| **Provider Base** | Shared abstract class | HTTP client, retry logic, timeout handling, error normalization |

Each provider implements a common `AiProviderInterface` so application code is fully provider-agnostic. Providers that do not support a given capability (e.g., ElevenLabs does not do chat) throw `UnsupportedCapabilityException` with a clear message.

### Phased Provider Rollout

**v1.0 (5 core providers):** Anthropic, OpenAI, Gemini, Ollama, Cohere
**v1.1 (add 5 more):** Azure OpenAI, DeepSeek, Groq, Mistral, OpenRouter
**v2.0 (add remaining):** xAI, ElevenLabs, Jina, VoyageAI

This phased approach ensures each provider is thoroughly tested before expanding scope.

## Core Features

### Chat Completion
Send single messages or full conversations to any provider. Pass model, temperature, maxTokens, topP, and stop sequences per call. The response includes content, token usage, finish reason, and the model identifier.

### Streaming
First-class streaming via generators. Iterate over `StreamChunk` objects as tokens arrive, or use the `onToken` callback for real-time output. Streaming tool calls accumulate partial JSON across chunks. Async iteration is supported for non-blocking pipelines.

### Tool / Function Calling
Define tools as PHP methods annotated with `#[AiTool]`. The framework auto-generates JSON Schema from method signatures, type hints, and docblocks. Multi-step tool loops run automatically: model calls tool, receives result, decides next action. Parameter validation runs before tool execution.

### Structured Output
Request typed PHP objects back from the model. `lattice/ai` uses provider-native JSON mode (OpenAI, Gemini) or assistant prefill (Anthropic) to coerce output. Schema validation and automatic retry (configurable) handle malformed responses. `ObjectSchema` provides a fluent builder for complex schemas.

### Agents
Autonomous AI agents that combine a system prompt, tools, a model, and a provider into a self-contained unit. Agents run multi-step reasoning loops, calling tools and evaluating results until they reach a conclusion.

- **Agent class**: Full-featured base with system prompt, tools, model, provider, and max iterations.
- **AnonymousAgent**: Inline agent definition for one-off tasks.
- **StructuredAgent**: Returns typed objects instead of free-form text.
- **`#[Agent]` attribute**: Declarative agent definition on classes.
- **Queued agents**: Dispatch long-running agents to the queue via `lattice/queue`.
- **Agent events**: `AgentStarted`, `ToolCalled`, `AgentCompleted` fired through `lattice/events`.

### Image Generation
Generate images, create edits, and produce variations through providers that support it (OpenAI, Azure OpenAI). Consistent API regardless of the underlying image model.

### Audio Synthesis & Transcription
Text-to-speech via ElevenLabs and OpenAI. Speech-to-text transcription via OpenAI (Whisper). Unified interface for both directions of audio conversion.

### Embeddings
Generate vector embeddings for text -- single or batch. Configure dimensionality where the provider allows. Supported by OpenAI, Gemini, Mistral, Ollama, Cohere, Jina, and VoyageAI.

### Reranking
Reorder a set of documents by relevance to a query. Supported by Cohere and Jina. Returns scored, sorted results ready for RAG pipelines.

### File Handling
Upload files and reference them in prompts. Supports provider-specific file APIs for context attachment, vision inputs, and document analysis.

## Middleware System

AI requests pass through a middleware pipeline before reaching the provider. Built-in middleware:

- **LoggingMiddleware**: Log every request and response with token counts, latency, and cost.
- **RateLimitMiddleware**: Per-provider rate limiting (RPM, TPM) via `lattice/rate-limit`.
- **CacheMiddleware**: Cache identical prompts via `lattice/cache` (deterministic requests only by default).
- **RetryMiddleware**: Automatic retry with exponential backoff and jitter on transient failures.

Custom middleware implements a simple `handle(AiRequest $request, Closure $next): AiResponse` interface.

## Store System

Conversation persistence through the `Store` interface:

- **DatabaseStore**: Persist conversations to the database via `lattice/database`.
- **FileStore**: Persist conversations to the filesystem as JSON.
- **NullStore**: No persistence (in-memory only, for stateless use cases).

Stores support loading, saving, listing, and deleting conversations. Agents and chat sessions use stores transparently.

## Prompts System

Reusable prompt templates with variable interpolation. Define prompts as classes or load from files. Compose prompts from fragments. Version and share prompts across the application.

## Attribute-Driven DI

```php
// Inject the default provider
public function __construct(
    private AiProviderInterface $ai,
) {}

// Inject a specific provider
public function __construct(
    #[AiProvider('anthropic')] private AiProviderInterface $claude,
    #[AiProvider('openai')] private AiProviderInterface $gpt,
) {}
```

The `#[AiProvider]` attribute integrates with LatticePHP's container. Without an argument, the default provider from config is injected. With a provider name, the specific implementation is resolved.

## Testing

`FakeProvider` ships with the package for deterministic testing:

- Queue scripted responses returned in order.
- Record all calls with full parameters.
- Fake streaming, embeddings, tool calls, and structured output.
- Assertion helpers: `assertPromptContains`, `assertToolCalled`, `assertModelUsed`, `assertProviderCalled`.
- Swap real providers with fakes in test setup -- zero API calls needed.

## Configuration

```php
// config/ai.php
return [
    'default' => env('AI_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 8192,
            'timeout' => 30,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORG_ID'),
            'model' => 'gpt-4o',
            'max_tokens' => 4096,
            'timeout' => 30,
        ],
        // ... 13 more providers
    ],

    'defaults' => [
        'temperature' => 1.0,
        'max_tokens' => 4096,
    ],

    'middleware' => [
        \Lattice\Ai\Middleware\RetryMiddleware::class,
        \Lattice\Ai\Middleware\LoggingMiddleware::class,
    ],

    'store' => 'database', // database, file, null
];
```

## Dependencies

| Package | Role |
|---|---|
| `lattice/core` | Service container, configuration, attributes |
| `lattice/http-client` | Outbound HTTP to provider APIs |
| `lattice/contracts` | Shared interfaces and value objects |
| `lattice/events` | Agent and request lifecycle events |
| `lattice/queue` | Queued agent execution |
| `lattice/cache` | Response caching middleware |
| `lattice/rate-limit` | Rate limiting middleware |
| `lattice/database` | DatabaseStore for conversation persistence |

## How lattice/ai Differs from Laravel AI

Laravel AI and `lattice/ai` target the same scope -- a unified, multi-provider AI SDK with full feature parity across chat, streaming, tools, structured output, agents, images, audio, embeddings, and reranking.

The key differences are architectural:

1. **Attribute-driven DI**: `lattice/ai` uses `#[AiProvider('anthropic')]`, `#[AiTool]`, and `#[Agent]` attributes instead of facade-based wiring. The container resolves everything from attributes -- no manual registration.
2. **Module system integration**: `lattice/ai` ships as an `AiModule` that registers with LatticePHP's module system, participating in compile-time discovery of tools, agents, and middleware.
3. **Compile-time tool discovery**: `#[AiTool]` methods are discovered and schema-generated at compile time, not runtime. This means zero reflection overhead during requests.
4. **Event-driven agents**: Agent lifecycle events (`AgentStarted`, `ToolCalled`, `AgentCompleted`) integrate with `lattice/events` for observability, logging, and custom hooks.
5. **Middleware-first architecture**: All AI requests pass through a configurable middleware stack. Logging, rate limiting, caching, and retry are middleware -- not baked into the provider implementations.

Feature parity is the baseline. The differentiator is how `lattice/ai` integrates with LatticePHP's attribute-driven, module-based architecture to provide a more declarative, compile-time-optimized developer experience.

## Inspiration

- **Laravel AI (laravel/ai)** -- unified multi-provider AI SDK with agents, tools, streaming, and queued execution.
- **Vercel AI SDK** -- streaming-first, multi-provider AI with tool calling and structured output.

## Success Criteria

1. All 15 providers work through a single `AiProviderInterface` -- chat, stream, tools, structured output function correctly per provider's capabilities.
2. Streaming works across all chat-capable providers with consistent `StreamChunk` iteration.
3. Tool calling works with `#[AiTool]` attribute, auto-schema generation, and multi-step tool loops.
4. Structured output returns typed PHP objects reliably with schema validation and retry.
5. Agent system supports autonomous multi-step reasoning with tools, queued execution, and event lifecycle.
6. Image generation, audio synthesis, audio transcription, embeddings, and reranking work through their respective providers.
7. Middleware stack (logging, rate limiting, caching, retry) is configurable and extensible.
8. Store system persists conversations to database or filesystem.
9. `FakeProvider` enables deterministic testing with assertion helpers and zero API calls.
10. Switching providers requires only a config change -- zero code modifications.
