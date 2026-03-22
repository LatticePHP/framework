<?php

declare(strict_types=1);

namespace Lattice\Auth\Events;

final class AuditEvent
{
    public readonly \DateTimeImmutable $timestamp;

    public function __construct(
        public readonly string $type,
        public readonly string $principalId,
        ?\DateTimeImmutable $timestamp = null,
        public readonly array $metadata = [],
    ) {
        $this->timestamp = $timestamp ?? new \DateTimeImmutable();
    }
}
