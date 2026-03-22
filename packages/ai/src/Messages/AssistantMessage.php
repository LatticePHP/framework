<?php

declare(strict_types=1);

namespace Lattice\Ai\Messages;

final readonly class AssistantMessage
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
    ) {}

    public static function create(string $content): self
    {
        return new self($content);
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    public static function withToolCalls(string $content, array $toolCalls): self
    {
        return new self($content, $toolCalls);
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    public function role(): string
    {
        return 'assistant';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => 'assistant',
            'content' => $this->content,
        ];

        if ($this->toolCalls !== []) {
            $data['tool_calls'] = array_map(
                static fn (ToolCall $tc): array => $tc->toArray(),
                $this->toolCalls,
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $toolCalls = [];
        if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
            foreach ($data['tool_calls'] as $tc) {
                $toolCalls[] = ToolCall::fromArray($tc);
            }
        }

        return new self($data['content'] ?? '', $toolCalls);
    }
}
