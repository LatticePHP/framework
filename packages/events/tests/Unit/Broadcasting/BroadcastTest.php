<?php

declare(strict_types=1);

namespace Lattice\Events\Tests\Unit\Broadcasting;

use Lattice\Events\Broadcasting\Broadcast;
use Lattice\Events\Broadcasting\BroadcastDriverInterface;
use Lattice\Events\Broadcasting\BroadcastInterceptor;
use Lattice\Events\Broadcasting\Drivers\NullBroadcastDriver;
use Lattice\Events\Broadcasting\FakeBroadcaster;
use Lattice\Events\Broadcasting\ShouldBroadcast;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BroadcastTest extends TestCase
{
    protected function setUp(): void
    {
        Broadcast::reset();
    }

    // ---------------------------------------------------------------
    // Channel authorization
    // ---------------------------------------------------------------

    public function test_channel_authorization_succeeds_when_callback_returns_true(): void
    {
        Broadcast::channel('orders.{orderId}', function (object $user, string $orderId): bool {
            return $orderId === '42';
        });

        $user = new \stdClass();

        $this->assertTrue(Broadcast::authorize('orders.42', $user));
    }

    public function test_channel_authorization_fails_when_callback_returns_false(): void
    {
        Broadcast::channel('orders.{orderId}', function (object $user, string $orderId): bool {
            return $orderId === '42';
        });

        $user = new \stdClass();

        $this->assertFalse(Broadcast::authorize('orders.99', $user));
    }

    public function test_channel_authorization_returns_false_for_unregistered_channel(): void
    {
        $user = new \stdClass();

        $this->assertFalse(Broadcast::authorize('unknown.channel', $user));
    }

    public function test_channel_authorization_can_return_array(): void
    {
        Broadcast::channel('presence.room.{roomId}', function (object $user, string $roomId): array {
            return ['id' => 1, 'name' => 'Alice', 'room' => $roomId];
        });

        $user = new \stdClass();
        $result = Broadcast::authorize('presence.room.lobby', $user);

        $this->assertIsArray($result);
        $this->assertSame('lobby', $result['room']);
        $this->assertSame('Alice', $result['name']);
    }

    // ---------------------------------------------------------------
    // Channel pattern matching with parameters
    // ---------------------------------------------------------------

    public function test_channel_pattern_with_multiple_parameters(): void
    {
        $captured = [];

        Broadcast::channel('chat.{teamId}.{roomId}', function (object $user, string $teamId, string $roomId) use (&$captured): bool {
            $captured = ['teamId' => $teamId, 'roomId' => $roomId];
            return true;
        });

        $user = new \stdClass();

        $this->assertTrue(Broadcast::authorize('chat.alpha.general', $user));
        $this->assertSame('alpha', $captured['teamId']);
        $this->assertSame('general', $captured['roomId']);
    }

    public function test_exact_channel_pattern_without_parameters(): void
    {
        Broadcast::channel('global-notifications', function (object $user): bool {
            return true;
        });

        $user = new \stdClass();

        $this->assertTrue(Broadcast::authorize('global-notifications', $user));
        $this->assertFalse(Broadcast::authorize('global-notifications.extra', $user));
    }

    public function test_pattern_does_not_match_partial_channel(): void
    {
        Broadcast::channel('orders.{id}', fn(object $user, string $id): bool => true);

        $user = new \stdClass();

        // "orders.42.details" should NOT match "orders.{id}" because {id} matches [^.]+
        $this->assertFalse(Broadcast::authorize('orders.42.details', $user));
    }

    // ---------------------------------------------------------------
    // Broadcasting events through the driver
    // ---------------------------------------------------------------

    public function test_broadcast_event_sends_to_driver(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string { return 'orders'; }
            public function broadcastAs(): string { return 'order.created'; }
            public function broadcastWith(): array { return ['id' => 1]; }
        };

        Broadcast::event($event);

        $broadcasts = $fake->getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertSame('orders', $broadcasts[0]['channels']);
        $this->assertSame('order.created', $broadcasts[0]['event']);
        $this->assertSame(['id' => 1], $broadcasts[0]['data']);
    }

    public function test_broadcast_event_throws_when_no_driver_set(): void
    {
        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string { return 'test'; }
            public function broadcastAs(): string { return 'test.event'; }
            public function broadcastWith(): array { return []; }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No broadcast driver configured');

        Broadcast::event($event);
    }

    public function test_broadcast_with_multiple_channels(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): array { return ['channel-a', 'channel-b', 'channel-c']; }
            public function broadcastAs(): string { return 'multi.event'; }
            public function broadcastWith(): array { return ['msg' => 'hello']; }
        };

        Broadcast::event($event);

        $broadcasts = $fake->getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertSame(['channel-a', 'channel-b', 'channel-c'], $broadcasts[0]['channels']);
    }

    // ---------------------------------------------------------------
    // FakeBroadcaster assertions
    // ---------------------------------------------------------------

    public function test_fake_broadcaster_assert_broadcast(): void
    {
        $fake = new FakeBroadcaster();
        $fake->broadcast('ch', 'user.registered', ['id' => 5]);

        $fake->assertBroadcast('user.registered');
        $fake->assertBroadcast('user.registered', times: 1);
    }

    public function test_fake_broadcaster_assert_not_broadcast(): void
    {
        $fake = new FakeBroadcaster();
        $fake->broadcast('ch', 'user.registered', []);

        $fake->assertNotBroadcast('order.created');
    }

    public function test_fake_broadcaster_assert_nothing_broadcast(): void
    {
        $fake = new FakeBroadcaster();

        $fake->assertNothingBroadcast();
    }

    public function test_fake_broadcaster_captures_multiple_broadcasts(): void
    {
        $fake = new FakeBroadcaster();
        $fake->broadcast('ch1', 'event.one', ['a' => 1]);
        $fake->broadcast('ch2', 'event.two', ['b' => 2]);
        $fake->broadcast('ch1', 'event.one', ['a' => 3]);

        $this->assertCount(3, $fake->getBroadcasts());
        $fake->assertBroadcast('event.one', times: 2);
        $fake->assertBroadcast('event.two', times: 1);
    }

    public function test_fake_broadcaster_flush_clears_broadcasts(): void
    {
        $fake = new FakeBroadcaster();
        $fake->broadcast('ch', 'event', []);

        $this->assertCount(1, $fake->getBroadcasts());

        $fake->flush();

        $fake->assertNothingBroadcast();
    }

    // ---------------------------------------------------------------
    // NullBroadcastDriver
    // ---------------------------------------------------------------

    public function test_null_driver_discards_silently(): void
    {
        $driver = new NullBroadcastDriver();

        // Should not throw or produce any side effects
        $driver->broadcast('channel', 'event.name', ['data' => true]);
        $driver->broadcast(['ch1', 'ch2'], 'another.event', []);

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // BroadcastInterceptor
    // ---------------------------------------------------------------

    public function test_interceptor_broadcasts_should_broadcast_events(): void
    {
        $fake = new FakeBroadcaster();
        $interceptor = new BroadcastInterceptor($fake);

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string { return 'notifications'; }
            public function broadcastAs(): string { return 'notification.created'; }
            public function broadcastWith(): array { return ['title' => 'Hello']; }
        };

        $interceptor->handle($event);

        $fake->assertBroadcast('notification.created');
        $this->assertSame('Hello', $fake->getBroadcasts()[0]['data']['title']);
    }

    public function test_interceptor_ignores_non_broadcastable_events(): void
    {
        $fake = new FakeBroadcaster();
        $interceptor = new BroadcastInterceptor($fake);

        $event = new \stdClass();

        $interceptor->handle($event);

        $fake->assertNothingBroadcast();
    }

    // ---------------------------------------------------------------
    // ShouldBroadcast interface contract
    // ---------------------------------------------------------------

    public function test_should_broadcast_interface_works_with_concrete_class(): void
    {
        $event = new TestOrderCreatedEvent(orderId: 99, total: 49.99);

        $this->assertSame('orders', $event->broadcastOn());
        $this->assertSame('order.created', $event->broadcastAs());
        $this->assertSame(['order_id' => 99, 'total' => 49.99], $event->broadcastWith());
        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    // ---------------------------------------------------------------
    // Reset
    // ---------------------------------------------------------------

    public function test_reset_clears_channels_and_driver(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);
        Broadcast::channel('test', fn() => true);

        $this->assertNotEmpty(Broadcast::getChannels());

        Broadcast::reset();

        $this->assertEmpty(Broadcast::getChannels());
        $this->expectException(RuntimeException::class);
        Broadcast::getDriver();
    }
}

/**
 * Concrete ShouldBroadcast event for testing.
 */
final class TestOrderCreatedEvent implements ShouldBroadcast
{
    public function __construct(
        private readonly int $orderId,
        private readonly float $total,
    ) {}

    public function broadcastOn(): string
    {
        return 'orders';
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'total' => $this->total,
        ];
    }
}
