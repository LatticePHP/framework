<?php

declare(strict_types=1);

namespace Lattice\Observability\Audit;

final class AuditEvent
{
    public function __construct(
        public readonly string $type,
        public readonly string $actor,
        public readonly string $target,
        public readonly string $action,
        public readonly array $metadata = [],
        public readonly float $timestamp = 0.0,
        public readonly ?string $correlationId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'actor' => $this->actor,
            'target' => $this->target,
            'action' => $this->action,
            'metadata' => $this->metadata ?: null,
            'timestamp' => $this->timestamp,
            'correlationId' => $this->correlationId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
