<?php

declare(strict_types=1);

namespace Lattice\Queue\Failed;

use Lattice\Queue\SerializedJob;

final class InMemoryFailedJobStore implements FailedJobStoreInterface
{
    /** @var array<string, FailedJob> */
    private array $failedJobs = [];

    public function store(SerializedJob $job, \Throwable $exception): void
    {
        $this->failedJobs[$job->id] = new FailedJob(
            id: $job->id,
            queue: $job->queue,
            payload: $job->payload,
            exception: $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            failedAt: new \DateTimeImmutable(),
        );
    }

    /** @return FailedJob[] */
    public function all(): array
    {
        return array_values($this->failedJobs);
    }

    public function find(string $id): ?FailedJob
    {
        return $this->failedJobs[$id] ?? null;
    }

    public function retry(string $id): void
    {
        if (!isset($this->failedJobs[$id])) {
            throw new \InvalidArgumentException("Failed job not found: {$id}");
        }

        unset($this->failedJobs[$id]);
    }

    public function delete(string $id): void
    {
        unset($this->failedJobs[$id]);
    }

    public function flush(): void
    {
        $this->failedJobs = [];
    }
}
