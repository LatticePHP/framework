<?php

declare(strict_types=1);

namespace Lattice\Queue\Testing;

use Lattice\Queue\JobInterface;
use PHPUnit\Framework\Assert;

final class QueueFake
{
    /** @var array<class-string, JobInterface[]> */
    private array $dispatched = [];

    public function dispatch(JobInterface $job): string
    {
        $this->dispatched[$job::class][] = $job;

        return bin2hex(random_bytes(16));
    }

    public function dispatchAfter(JobInterface $job, int $delaySeconds): string
    {
        return $this->dispatch($job);
    }

    /**
     * @param class-string $jobClass
     */
    public function assertDispatched(string $jobClass): void
    {
        Assert::assertNotEmpty(
            $this->dispatched[$jobClass] ?? [],
            "The expected [{$jobClass}] job was not dispatched.",
        );
    }

    /**
     * @param class-string $jobClass
     */
    public function assertNotDispatched(string $jobClass): void
    {
        Assert::assertEmpty(
            $this->dispatched[$jobClass] ?? [],
            "The unexpected [{$jobClass}] job was dispatched.",
        );
    }

    /**
     * @param class-string $jobClass
     */
    public function assertDispatchedCount(string $jobClass, int $count): void
    {
        $actual = count($this->dispatched[$jobClass] ?? []);

        Assert::assertSame(
            $count,
            $actual,
            "Expected [{$jobClass}] to be dispatched {$count} times, but was dispatched {$actual} times.",
        );
    }

    /**
     * @param class-string<T> $jobClass
     * @return T[]
     *
     * @template T of JobInterface
     */
    public function getDispatched(string $jobClass): array
    {
        return $this->dispatched[$jobClass] ?? [];
    }
}
