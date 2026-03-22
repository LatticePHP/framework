<?php

declare(strict_types=1);

namespace Lattice\Queue;

abstract class AbstractJob implements JobInterface
{
    protected string $queue = 'default';

    protected string $connection = 'default';

    protected int $maxAttempts = 3;

    protected int $timeout = 60;

    /** @var int[] */
    protected array $backoff = [1, 5, 30];

    abstract public function handle(): void;

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /** @return int[] */
    public function getBackoff(): array
    {
        return $this->backoff;
    }
}
