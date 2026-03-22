<?php

declare(strict_types=1);

namespace Lattice\Observability\Health;

final class HealthResult
{
    public function __construct(
        public readonly HealthStatus $status,
        public readonly string $message = '',
        public readonly array $metadata = [],
        public readonly float $duration = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'duration' => $this->duration,
        ];
    }
}
