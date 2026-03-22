<?php

declare(strict_types=1);

namespace Lattice\Ai\Providers;

use Generator;
use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Responses\AiResponse;
use Lattice\Ai\Responses\StreamChunk;

interface ProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResponse;

    /**
     * Send a streaming chat completion request.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     * @return Generator<int, StreamChunk>
     */
    public function stream(array $messages, array $options = []): Generator;

    /**
     * Get the provider name.
     */
    public function name(): string;

    /**
     * Get the list of capabilities supported by this provider.
     *
     * @return list<ProviderCapability>
     */
    public function capabilities(): array;

    /**
     * Check if the provider supports a specific capability.
     */
    public function supports(ProviderCapability $capability): bool;
}
