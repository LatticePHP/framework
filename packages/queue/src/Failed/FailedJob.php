<?php

declare(strict_types=1);

namespace Lattice\Queue\Failed;

final class FailedJob
{
    public function __construct(
        public readonly string $id,
        public readonly string $queue,
        public readonly string $payload,
        public readonly string $exception,
        public readonly \DateTimeImmutable $failedAt,
    ) {
    }
}
