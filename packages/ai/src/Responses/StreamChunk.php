<?php

declare(strict_types=1);

namespace Lattice\Ai\Responses;

final readonly class StreamChunk
{
    public function __construct(
        public string $delta,
        public bool $isFinal = false,
        public ?FinishReason $finishReason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'delta' => $this->delta,
            'is_final' => $this->isFinal,
            'finish_reason' => $this->finishReason?->value,
        ];
    }
}
