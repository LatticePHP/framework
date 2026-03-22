<?php

declare(strict_types=1);

namespace Lattice\Testing\Fakes;

use PHPUnit\Framework\Assert;

/**
 * Captures dispatched jobs for assertion in tests.
 */
final class FakeQueueDispatcher
{
    /** @var array<class-string, list<object>> */
    private array $dispatched = [];

    public function dispatch(object $job): string
    {
        $this->dispatched[$job::class][] = $job;

        return 'fake-job-id-' . count($this->dispatched[$job::class]);
    }

    /**
     * Assert that a job of the given class was dispatched.
     *
     * @param class-string $jobClass
     */
    public function assertDispatched(string $jobClass): void
    {
        Assert::assertNotEmpty(
            $this->dispatched[$jobClass] ?? [],
            sprintf('Expected job [%s] was not dispatched.', $jobClass)
        );
    }

    /**
     * Assert that a job of the given class was NOT dispatched.
     *
     * @param class-string $jobClass
     */
    public function assertNotDispatched(string $jobClass): void
    {
        Assert::assertEmpty(
            $this->dispatched[$jobClass] ?? [],
            sprintf('Unexpected job [%s] was dispatched.', $jobClass)
        );
    }

    /**
     * Get all dispatched jobs of the given class.
     *
     * @param class-string $jobClass
     * @return list<object>
     */
    public function getDispatched(string $jobClass): array
    {
        return $this->dispatched[$jobClass] ?? [];
    }
}
