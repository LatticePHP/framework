<?php

declare(strict_types=1);

namespace Lattice\Ai\Messages;

final readonly class ToolResult
{
    public function __construct(
        public string $toolCallId,
        public string $content,
        public bool $isError = false,
    ) {}

    public function role(): string
    {
        return 'tool';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => 'tool',
            'tool_call_id' => $this->toolCallId,
            'content' => $this->content,
            'is_error' => $this->isError,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['tool_call_id'],
            $data['content'],
            $data['is_error'] ?? false,
        );
    }
}
