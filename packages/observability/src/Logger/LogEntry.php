<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

final class LogEntry
{
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly array $context,
        public readonly float $timestamp,
        public readonly ?string $correlationId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context ?: null,
            'timestamp' => $this->timestamp,
            'correlationId' => $this->correlationId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
