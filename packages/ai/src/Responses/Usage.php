<?php

declare(strict_types=1);

namespace Lattice\Ai\Responses;

final readonly class Usage
{
    public int $totalTokens;

    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
    ) {
        $this->totalTokens = $this->promptTokens + $this->completionTokens;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    /**
     * @param array<string, int> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['prompt_tokens'] ?? 0,
            $data['completion_tokens'] ?? 0,
        );
    }
}
