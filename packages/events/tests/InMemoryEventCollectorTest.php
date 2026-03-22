<?php

declare(strict_types=1);

namespace Lattice\Events\Tests;

use Lattice\Events\Testing\InMemoryEventCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryEventCollectorTest extends TestCase
{
    #[Test]
    public function it_captures_dispatched_events(): void
    {
        $collector = new InMemoryEventCollector();

        $event1 = new \stdClass();
        $event2 = new class {};

        $collector->dispatch($event1);
        $collector->dispatch($event2);

        $dispatched = $collector->getDispatched();
        $this->assertCount(2, $dispatched);
        $this->assertSame($event1, $dispatched[0]);
    }

    #[Test]
    public function it_asserts_event_was_dispatched(): void
    {
        $collector = new InMemoryEventCollector();
        $event = new \stdClass();
        $collector->dispatch($event);

        // Should not throw
        $collector->assertDispatched(\stdClass::class);
    }

    #[Test]
    public function it_fails_assert_dispatched_when_event_not_dispatched(): void
    {
        $collector = new InMemoryEventCollector();

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $collector->assertDispatched(\stdClass::class);
    }

    #[Test]
    public function it_asserts_event_was_not_dispatched(): void
    {
        $collector = new InMemoryEventCollector();

        // Should not throw
        $collector->assertNotDispatched(\stdClass::class);
    }

    #[Test]
    public function it_fails_assert_not_dispatched_when_event_was_dispatched(): void
    {
        $collector = new InMemoryEventCollector();
        $collector->dispatch(new \stdClass());

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $collector->assertNotDispatched(\stdClass::class);
    }

    #[Test]
    public function it_returns_event_from_dispatch(): void
    {
        $collector = new InMemoryEventCollector();
        $event = new \stdClass();

        $result = $collector->dispatch($event);

        $this->assertSame($event, $result);
    }
}
