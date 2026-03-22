<?php

declare(strict_types=1);

namespace Lattice\Ai\Responses;

use Lattice\Ai\Messages\ToolCall;

final readonly class AiResponse
{
    /**
     * @param list<ToolCall> $toolCalls
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public string $content,
        public Usage $usage,
        public FinishReason $finishReason,
        public array $toolCalls = [],
        public string $model = '',
        public array $rawResponse = [],
    ) {}

    public function getText(): string
    {
        return $this->content;
    }

    public function getUsage(): Usage
    {
        return $this->usage;
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * @return list<ToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function isComplete(): bool
    {
        return $this->finishReason === FinishReason::Stop;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'usage' => $this->usage->toArray(),
            'finish_reason' => $this->finishReason->value,
            'tool_calls' => array_map(
                static fn (ToolCall $tc): array => $tc->toArray(),
                $this->toolCalls,
            ),
            'model' => $this->model,
        ];
    }
}
