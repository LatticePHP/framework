<?php

declare(strict_types=1);

namespace Lattice\Ripple\Tests\Broadcasting;

use Lattice\Ripple\Broadcasting\BroadcastAttribute;
use Lattice\Ripple\Broadcasting\Broadcaster;
use Lattice\Ripple\Server\ConnectionManager;
use Lattice\Ripple\Server\Frame;
use PHPUnit\Framework\TestCase;

final class BroadcasterTest extends TestCase
{
    private function createSocketPair(): array
    {
        if (!extension_loaded('sockets')) {
            $this->markTestSkipped('ext-sockets is required for Broadcaster tests.');
        }

        $sockets = [];

        // AF_UNIX is not supported on Windows; use AF_INET instead
        if (PHP_OS_FAMILY === 'Windows') {
            if (!@socket_create_pair(AF_INET, SOCK_STREAM, SOL_TCP, $sockets)) {
                $this->markTestSkipped('Could not create socket pair on Windows.');
            }
        } else {
            if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
                $this->markTestSkipped('Could not create socket pair.');
            }
        }

        return $sockets;
    }

    // --- broadcastToChannel ---

    public function test_broadcast_to_channel(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '127.0.0.2', 2000);

            $conn1->subscribe('chat');
            $conn2->subscribe('chat');

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToChannel('chat', 'message', ['text' => 'Hello']);

            // Both connections should have received the message
            $this->assertSame(1, $conn1->getMessagesSent());
            $this->assertSame(1, $conn2->getMessagesSent());

            // Read what was sent to conn1 via the paired socket
            $raw = socket_read($s2, 65536);
            $this->assertNotEmpty($raw);

            // Decode the WebSocket frame
            [$frame, ] = Frame::decode($raw);
            $decoded = json_decode($frame->payload, true);

            $this->assertSame('message', $decoded['event']);
            $this->assertSame('chat', $decoded['channel']);
            $this->assertSame(['text' => 'Hello'], $decoded['data']);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_broadcast_to_channel_only_sends_to_subscribers(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '127.0.0.2', 2000);

            $conn1->subscribe('chat');
            // conn2 does NOT subscribe to 'chat'

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToChannel('chat', 'message', ['text' => 'Hello']);

            $this->assertSame(1, $conn1->getMessagesSent());
            $this->assertSame(0, $conn2->getMessagesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    // --- broadcastToAll ---

    public function test_broadcast_to_all(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '127.0.0.2', 2000);

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToAll('system', ['status' => 'ok']);

            $this->assertSame(1, $conn1->getMessagesSent());
            $this->assertSame(1, $conn2->getMessagesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    // --- broadcastToConnection ---

    public function test_broadcast_to_specific_connection(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '127.0.0.2', 2000);

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToConnection($conn1->id, 'private-msg', ['text' => 'Hi']);

            $this->assertSame(1, $conn1->getMessagesSent());
            $this->assertSame(0, $conn2->getMessagesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_broadcast_to_nonexistent_connection(): void
    {
        $manager = new ConnectionManager();
        $broadcaster = new Broadcaster($manager);

        // Should not throw
        $broadcaster->broadcastToConnection('nonexistent', 'test', []);
        $this->assertTrue(true);
    }

    // --- broadcastToChannelExcept ---

    public function test_broadcast_to_channel_except_sender(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();
        [$s5, $s6] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '127.0.0.2', 2000);
            $conn3 = $manager->accept($s5, '127.0.0.3', 3000);

            $conn1->subscribe('chat');
            $conn2->subscribe('chat');
            $conn3->subscribe('chat');

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToChannelExcept('chat', 'message', ['text' => 'Hi'], $conn1->id);

            // conn1 excluded, conn2 and conn3 should receive
            $this->assertSame(0, $conn1->getMessagesSent());
            $this->assertSame(1, $conn2->getMessagesSent());
            $this->assertSame(1, $conn3->getMessagesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
            @socket_close($s5);
            @socket_close($s6);
        }
    }

    // --- broadcastToConnections ---

    public function test_broadcast_to_set_of_connections(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();
        [$s5, $s6] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '127.0.0.2', 2000);
            $conn3 = $manager->accept($s5, '127.0.0.3', 3000);

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToConnections([$conn1->id, $conn3->id], 'update', ['val' => 1]);

            $this->assertSame(1, $conn1->getMessagesSent());
            $this->assertSame(0, $conn2->getMessagesSent());
            $this->assertSame(1, $conn3->getMessagesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
            @socket_close($s5);
            @socket_close($s6);
        }
    }

    // --- BroadcastAttribute ---

    public function test_broadcast_attribute_resolves_channels(): void
    {
        $attr = new BroadcastAttribute(channel: 'orders.{orderId}');

        $event = new class {
            public int $orderId = 42;
        };

        $channels = $attr->resolveChannels($event);
        $this->assertSame(['orders.42'], $channels);
    }

    public function test_broadcast_attribute_resolves_multiple_channels(): void
    {
        $attr = new BroadcastAttribute(channel: ['orders.{orderId}', 'user.{userId}']);

        $event = new class {
            public int $orderId = 42;
            public string $userId = 'abc';
        };

        $channels = $attr->resolveChannels($event);
        $this->assertSame(['orders.42', 'user.abc'], $channels);
    }

    public function test_broadcast_attribute_custom_event_name(): void
    {
        $attr = new BroadcastAttribute(channel: 'test', as: 'custom.event');

        $event = new \stdClass();
        $this->assertSame('custom.event', $attr->resolveEventName($event));
    }

    public function test_broadcast_attribute_default_event_name_uses_class_basename(): void
    {
        $attr = new BroadcastAttribute(channel: 'test');

        $event = new \stdClass();
        $this->assertSame('stdClass', $attr->resolveEventName($event));
    }

    public function test_broadcast_attribute_unresolvable_placeholder_left_intact(): void
    {
        $attr = new BroadcastAttribute(channel: 'orders.{missingProp}');

        $event = new class {
            public int $orderId = 42;
        };

        $channels = $attr->resolveChannels($event);
        $this->assertSame(['orders.{missingProp}'], $channels);
    }

    // --- Message envelope format ---

    public function test_message_envelope_with_channel(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn = $manager->accept($s1, '127.0.0.1', 1000);
            $conn->subscribe('notifications');

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToChannel('notifications', 'alert', ['level' => 'warn']);

            $raw = socket_read($s2, 65536);
            [$frame, ] = Frame::decode($raw);
            $decoded = json_decode($frame->payload, true);

            $this->assertArrayHasKey('event', $decoded);
            $this->assertArrayHasKey('channel', $decoded);
            $this->assertArrayHasKey('data', $decoded);
            $this->assertSame('alert', $decoded['event']);
            $this->assertSame('notifications', $decoded['channel']);
            $this->assertSame(['level' => 'warn'], $decoded['data']);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }

    public function test_message_envelope_without_channel(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn = $manager->accept($s1, '127.0.0.1', 1000);

            $broadcaster = new Broadcaster($manager);
            $broadcaster->broadcastToAll('ping', []);

            $raw = socket_read($s2, 65536);
            [$frame, ] = Frame::decode($raw);
            $decoded = json_decode($frame->payload, true);

            $this->assertArrayHasKey('event', $decoded);
            $this->assertArrayNotHasKey('channel', $decoded);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }
}
