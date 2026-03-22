<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests;

use Lattice\Testing\Fakes\FakeEventBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeEventBusTest extends TestCase
{
    #[Test]
    public function it_captures_dispatched_events(): void
    {
        $bus = new FakeEventBus();

        $event = new \stdClass();
        $bus->dispatch($event);

        $bus->assertDispatched(\stdClass::class);
    }

    #[Test]
    public function it_fails_when_event_was_not_dispatched(): void
    {
        $bus = new FakeEventBus();

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $bus->assertDispatched(\stdClass::class);
    }

    #[Test]
    public function it_asserts_event_was_not_dispatched(): void
    {
        $bus = new FakeEventBus();

        $bus->assertNotDispatched(\stdClass::class);

        // Should not throw
        $this->assertTrue(true);
    }

    #[Test]
    public function it_fails_when_event_was_dispatched_but_should_not(): void
    {
        $bus = new FakeEventBus();
        $bus->dispatch(new \stdClass());

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $bus->assertNotDispatched(\stdClass::class);
    }

    #[Test]
    public function it_tracks_multiple_event_types(): void
    {
        $bus = new FakeEventBus();

        $bus->dispatch(new \stdClass());
        $bus->dispatch(new \RuntimeException('test'));

        $bus->assertDispatched(\stdClass::class);
        $bus->assertDispatched(\RuntimeException::class);
        $bus->assertNotDispatched(\LogicException::class);
    }

    #[Test]
    public function it_tracks_multiple_dispatches_of_same_type(): void
    {
        $bus = new FakeEventBus();

        $bus->dispatch(new \stdClass());
        $bus->dispatch(new \stdClass());

        $events = $bus->getDispatched(\stdClass::class);

        $this->assertCount(2, $events);
    }

    #[Test]
    public function it_returns_empty_array_for_undispatched_type(): void
    {
        $bus = new FakeEventBus();

        $events = $bus->getDispatched(\stdClass::class);

        $this->assertSame([], $events);
    }
}
