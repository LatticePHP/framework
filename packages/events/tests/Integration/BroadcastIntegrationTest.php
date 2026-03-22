<?php

declare(strict_types=1);

namespace Lattice\Events\Tests\Integration;

use Lattice\Events\Broadcasting\Broadcast;
use Lattice\Events\Broadcasting\BroadcastDriverInterface;
use Lattice\Events\Broadcasting\Drivers\LogBroadcastDriver;
use Lattice\Events\Broadcasting\Drivers\NullBroadcastDriver;
use Lattice\Events\Broadcasting\FakeBroadcaster;
use Lattice\Events\Broadcasting\ShouldBroadcast;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class BroadcastIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Broadcast::reset();
    }

    protected function tearDown(): void
    {
        Broadcast::reset();
    }

    #[Test]
    public function test_broadcast_event_dispatches_to_null_driver_without_error(): void
    {
        Broadcast::setDriver(new NullBroadcastDriver());

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string|array
            {
                return 'test-channel';
            }

            public function broadcastAs(): string
            {
                return 'test.event';
            }

            public function broadcastWith(): array
            {
                return ['key' => 'value'];
            }
        };

        // Should not throw
        Broadcast::event($event);
        $this->assertTrue(true);
    }

    #[Test]
    public function test_fake_broadcaster_captures_and_asserts_broadcast(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string|array
            {
                return 'orders';
            }

            public function broadcastAs(): string
            {
                return 'order.created';
            }

            public function broadcastWith(): array
            {
                return ['order_id' => 42];
            }
        };

        Broadcast::event($event);

        $fake->assertBroadcast('order.created');
    }

    #[Test]
    public function test_fake_broadcaster_assertNothingBroadcast_passes_when_empty(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);

        $fake->assertNothingBroadcast();
    }

    #[Test]
    public function test_channel_auth_allows_when_callback_returns_true(): void
    {
        Broadcast::setDriver(new NullBroadcastDriver());

        Broadcast::channel('private-chat', fn (object $user): bool => true);

        $user = new \stdClass();
        $result = Broadcast::authorize('private-chat', $user);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_channel_auth_denies_when_callback_returns_false(): void
    {
        Broadcast::setDriver(new NullBroadcastDriver());

        Broadcast::channel('private-chat', fn (object $user): bool => false);

        $user = new \stdClass();
        $result = Broadcast::authorize('private-chat', $user);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_channel_with_parameters_extracts_id(): void
    {
        Broadcast::setDriver(new NullBroadcastDriver());

        $capturedId = null;
        Broadcast::channel('workspace.{id}', function (object $user, string $id) use (&$capturedId): bool {
            $capturedId = $id;
            return true;
        });

        $user = new \stdClass();
        $result = Broadcast::authorize('workspace.42', $user);

        $this->assertTrue($result);
        $this->assertSame('42', $capturedId);
    }

    #[Test]
    public function test_log_broadcast_driver_logs_entry(): void
    {
        $entries = [];

        $logger = new class($entries) extends AbstractLogger {
            /** @param array<int, array{level: string, message: string, context: array<string, mixed>}> $entries */
            public function __construct(private array &$entries) {}

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->entries[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $driver = new LogBroadcastDriver($logger);
        Broadcast::setDriver($driver);

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string|array
            {
                return 'log-channel';
            }

            public function broadcastAs(): string
            {
                return 'log.test';
            }

            public function broadcastWith(): array
            {
                return ['foo' => 'bar'];
            }
        };

        Broadcast::event($event);

        $this->assertCount(1, $entries);
        $this->assertSame('info', $entries[0]['level']);
        $this->assertSame('Broadcasting event', $entries[0]['message']);
        $this->assertSame('log-channel', $entries[0]['context']['channels']);
        $this->assertSame('log.test', $entries[0]['context']['event']);
        $this->assertSame(['foo' => 'bar'], $entries[0]['context']['data']);
    }

    #[Test]
    public function test_multiple_channels_both_appear_in_driver(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);

        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string|array
            {
                return ['channel-a', 'channel-b'];
            }

            public function broadcastAs(): string
            {
                return 'multi.event';
            }

            public function broadcastWith(): array
            {
                return ['data' => true];
            }
        };

        Broadcast::event($event);

        $broadcasts = $fake->getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertSame(['channel-a', 'channel-b'], $broadcasts[0]['channels']);
        $this->assertSame('multi.event', $broadcasts[0]['event']);
    }

    #[Test]
    public function test_full_cycle_should_broadcast_event_captured_by_fake(): void
    {
        $fake = new FakeBroadcaster();
        Broadcast::setDriver($fake);

        // Define a concrete ShouldBroadcast event
        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string|array
            {
                return ['notifications', 'dashboard'];
            }

            public function broadcastAs(): string
            {
                return 'user.registered';
            }

            public function broadcastWith(): array
            {
                return [
                    'user_id' => 99,
                    'email' => 'test@example.com',
                ];
            }
        };

        // Dispatch through the Broadcast facade
        Broadcast::event($event);

        // Verify the FakeBroadcaster captured it correctly
        $fake->assertBroadcast('user.registered', 1);

        $broadcasts = $fake->getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertSame(['notifications', 'dashboard'], $broadcasts[0]['channels']);
        $this->assertSame('user.registered', $broadcasts[0]['event']);
        $this->assertSame(99, $broadcasts[0]['data']['user_id']);
        $this->assertSame('test@example.com', $broadcasts[0]['data']['email']);
    }
}
