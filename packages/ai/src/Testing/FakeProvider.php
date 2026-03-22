<?php

declare(strict_types=1);

namespace Lattice\Ai\Testing;

use Generator;
use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Providers\ProviderCapability;
use Lattice\Ai\Providers\ProviderInterface;
use Lattice\Ai\Responses\AiResponse;
use Lattice\Ai\Responses\FinishReason;
use Lattice\Ai\Responses\StreamChunk;
use Lattice\Ai\Responses\Usage;

final class FakeProvider implements ProviderInterface
{
    /** @var list<AiResponse> */
    private array $responseQueue = [];

    /** @var list<array{messages: list<UserMessage|AssistantMessage|SystemMessage|ToolResult>, options: array<string, mixed>}> */
    private array $recorded = [];

    private int $responseIndex = 0;

    /**
     * Queue a response to be returned by the next chat() or stream() call.
     */
    public function addResponse(AiResponse $response): self
    {
        $this->responseQueue[] = $response;

        return $this;
    }

    /**
     * Queue multiple responses.
     *
     * @param list<AiResponse> $responses
     */
    public function addResponses(array $responses): self
    {
        foreach ($responses as $response) {
            $this->addResponse($response);
        }

        return $this;
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $this->recorded[] = ['messages' => $messages, 'options' => $options];

        return $this->nextResponse();
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $this->recorded[] = ['messages' => $messages, 'options' => $options];

        $response = $this->nextResponse();

        // Split content into chunks for streaming simulation
        $content = $response->content;
        $words = $content !== '' ? explode(' ', $content) : [''];

        foreach ($words as $i => $word) {
            $isLast = $i === count($words) - 1;
            $delta = $i > 0 ? ' ' . $word : $word;

            yield new StreamChunk(
                $delta,
                $isLast,
                $isLast ? $response->finishReason : null,
            );
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    /**
     * @return list<ProviderCapability>
     */
    public function capabilities(): array
    {
        return [
            ProviderCapability::Chat,
            ProviderCapability::Streaming,
            ProviderCapability::ToolCalling,
            ProviderCapability::StructuredOutput,
            ProviderCapability::Embeddings,
            ProviderCapability::Vision,
        ];
    }

    public function supports(ProviderCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * Get all recorded calls.
     *
     * @return list<array{messages: list<UserMessage|AssistantMessage|SystemMessage|ToolResult>, options: array<string, mixed>}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /**
     * Get the number of calls made.
     */
    public function callCount(): int
    {
        return count($this->recorded);
    }

    /**
     * Assert that a prompt containing the given text was sent.
     *
     * @throws \RuntimeException
     */
    public function assertPromptContains(string $text): void
    {
        foreach ($this->recorded as $call) {
            foreach ($call['messages'] as $message) {
                $content = match (true) {
                    $message instanceof UserMessage => is_string($message->content) ? $message->content : json_encode($message->content),
                    $message instanceof AssistantMessage => $message->content,
                    $message instanceof SystemMessage => $message->content,
                    $message instanceof ToolResult => $message->content,
                    default => '',
                };

                if (str_contains((string) $content, $text)) {
                    return;
                }
            }
        }

        throw new \RuntimeException("Expected prompt to contain [{$text}], but it was not found.");
    }

    /**
     * Assert that no prompt containing the given text was sent.
     *
     * @throws \RuntimeException
     */
    public function assertPromptNotContains(string $text): void
    {
        foreach ($this->recorded as $call) {
            foreach ($call['messages'] as $message) {
                $content = match (true) {
                    $message instanceof UserMessage => is_string($message->content) ? $message->content : json_encode($message->content),
                    $message instanceof AssistantMessage => $message->content,
                    $message instanceof SystemMessage => $message->content,
                    $message instanceof ToolResult => $message->content,
                    default => '',
                };

                if (str_contains((string) $content, $text)) {
                    throw new \RuntimeException("Expected prompt NOT to contain [{$text}], but it was found.");
                }
            }
        }
    }

    /**
     * Assert that the provider was called exactly N times.
     *
     * @throws \RuntimeException
     */
    public function assertCallCount(int $expected): void
    {
        $actual = count($this->recorded);
        if ($actual !== $expected) {
            throw new \RuntimeException("Expected [{$expected}] calls, but [{$actual}] were made.");
        }
    }

    /**
     * Assert that nothing was sent.
     *
     * @throws \RuntimeException
     */
    public function assertNothingSent(): void
    {
        if ($this->recorded !== []) {
            throw new \RuntimeException(
                'Expected no calls, but ' . count($this->recorded) . ' were made.',
            );
        }
    }

    /**
     * Assert that a specific model was used.
     *
     * @throws \RuntimeException
     */
    public function assertModelUsed(string $model): void
    {
        foreach ($this->recorded as $call) {
            if (($call['options']['model'] ?? '') === $model) {
                return;
            }
        }

        throw new \RuntimeException("Expected model [{$model}] to be used, but it was not found in any call.");
    }

    /**
     * Get the next response from the queue.
     */
    private function nextResponse(): AiResponse
    {
        if ($this->responseQueue === []) {
            // Return a default empty response
            return new AiResponse(
                content: '',
                usage: new Usage(0, 0),
                finishReason: FinishReason::Stop,
            );
        }

        if ($this->responseIndex >= count($this->responseQueue)) {
            // Cycle back to the beginning
            $this->responseIndex = 0;
        }

        return $this->responseQueue[$this->responseIndex++];
    }
}
