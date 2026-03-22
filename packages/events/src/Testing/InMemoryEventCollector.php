<?php

declare(strict_types=1);

namespace Lattice\Events\Testing;

use PHPUnit\Framework\Assert;

final class InMemoryEventCollector
{
    /** @var object[] */
    private array $dispatched = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->dispatched[] = $event;

        return $event;
    }

    /**
     * @return object[]
     */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    public function assertDispatched(string $eventClass): void
    {
        foreach ($this->dispatched as $event) {
            if ($event instanceof $eventClass) {
                Assert::assertTrue(true);
                return;
            }
        }

        Assert::fail("Expected event [{$eventClass}] was not dispatched.");
    }

    public function assertNotDispatched(string $eventClass): void
    {
        foreach ($this->dispatched as $event) {
            if ($event instanceof $eventClass) {
                Assert::fail("Unexpected event [{$eventClass}] was dispatched.");
            }
        }

        Assert::assertTrue(true);
    }
}
