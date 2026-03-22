<?php

declare(strict_types=1);

namespace Lattice\Queue\Driver;

use Lattice\Queue\SerializedJob;

final class InMemoryDriver implements QueueDriverInterface
{
    /** @var array<string, SerializedJob[]> */
    private array $queues = [];

    /** @var SerializedJob[] */
    private array $dispatched = [];

    /** @var SerializedJob[] */
    private array $acknowledged = [];

    /** @var array<string, \Throwable> */
    private array $failed = [];

    public function push(string $queue, SerializedJob $job): string
    {
        $this->queues[$queue][] = $job;
        $this->dispatched[] = $job;

        return $job->id;
    }

    public function pushDelayed(string $queue, SerializedJob $job, int $delaySeconds): string
    {
        $delayed = $job->withAvailableAt(
            new \DateTimeImmutable("+{$delaySeconds} seconds"),
        );

        return $this->push($queue, $delayed);
    }

    public function pop(string $queue): ?SerializedJob
    {
        if (empty($this->queues[$queue])) {
            return null;
        }

        return array_shift($this->queues[$queue]);
    }

    public function acknowledge(string $jobId): void
    {
        $this->acknowledged[] = $this->findDispatched($jobId);
    }

    public function fail(string $jobId, \Throwable $reason): void
    {
        $this->failed[$jobId] = $reason;
    }

    public function size(string $queue): int
    {
        return count($this->queues[$queue] ?? []);
    }

    /** @return SerializedJob[] */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    /** @return SerializedJob[] */
    public function getAcknowledged(): array
    {
        return $this->acknowledged;
    }

    /** @return array<string, \Throwable> */
    public function getFailed(): array
    {
        return $this->failed;
    }

    private function findDispatched(string $jobId): ?SerializedJob
    {
        foreach ($this->dispatched as $job) {
            if ($job->id === $jobId) {
                return $job;
            }
        }

        return null;
    }
}
