<?php

declare(strict_types=1);

namespace Lattice\Testing\Fakes;

use PHPUnit\Framework\Assert;

/**
 * Captures dispatched events for assertion in tests.
 */
final class FakeEventBus
{
    /** @var array<class-string, list<object>> */
    private array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[$event::class][] = $event;
    }

    /**
     * Assert that an event of the given class was dispatched.
     *
     * @param class-string $eventClass
     */
    public function assertDispatched(string $eventClass): void
    {
        Assert::assertNotEmpty(
            $this->dispatched[$eventClass] ?? [],
            sprintf('Expected event [%s] was not dispatched.', $eventClass)
        );
    }

    /**
     * Assert that an event of the given class was NOT dispatched.
     *
     * @param class-string $eventClass
     */
    public function assertNotDispatched(string $eventClass): void
    {
        Assert::assertEmpty(
            $this->dispatched[$eventClass] ?? [],
            sprintf('Unexpected event [%s] was dispatched.', $eventClass)
        );
    }

    /**
     * Get all dispatched events of the given class.
     *
     * @param class-string $eventClass
     * @return list<object>
     */
    public function getDispatched(string $eventClass): array
    {
        return $this->dispatched[$eventClass] ?? [];
    }
}
