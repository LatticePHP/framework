<?php

declare(strict_types=1);

namespace Lattice\Queue;

final class SerializedJob
{
    public function __construct(
        public readonly string $id,
        public readonly string $queue,
        public readonly string $payload,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly int $timeout,
        public readonly \DateTimeImmutable $availableAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromJob(JobInterface $job, int $delaySeconds = 0): self
    {
        $now = new \DateTimeImmutable();
        $availableAt = $delaySeconds > 0
            ? $now->modify("+{$delaySeconds} seconds")
            : $now;

        return new self(
            id: bin2hex(random_bytes(16)),
            queue: $job->getQueue(),
            payload: serialize($job),
            attempts: 0,
            maxAttempts: $job->getMaxAttempts(),
            timeout: $job->getTimeout(),
            availableAt: $availableAt,
            createdAt: $now,
        );
    }

    public function toJob(): JobInterface
    {
        $job = unserialize($this->payload);

        if (!$job instanceof JobInterface) {
            throw new \RuntimeException('Failed to deserialize job payload into a JobInterface instance.');
        }

        return $job;
    }

    public function withIncrementedAttempts(): self
    {
        return new self(
            id: $this->id,
            queue: $this->queue,
            payload: $this->payload,
            attempts: $this->attempts + 1,
            maxAttempts: $this->maxAttempts,
            timeout: $this->timeout,
            availableAt: $this->availableAt,
            createdAt: $this->createdAt,
        );
    }

    public function withAvailableAt(\DateTimeImmutable $availableAt): self
    {
        return new self(
            id: $this->id,
            queue: $this->queue,
            payload: $this->payload,
            attempts: $this->attempts,
            maxAttempts: $this->maxAttempts,
            timeout: $this->timeout,
            availableAt: $availableAt,
            createdAt: $this->createdAt,
        );
    }
}
