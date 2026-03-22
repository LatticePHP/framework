<?php

declare(strict_types=1);

namespace Lattice\Ripple\Tests\Server;

use Lattice\Ripple\Server\ConnectionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionManagerTest extends TestCase
{
    private function createSocketPair(): array
    {
        if (!extension_loaded('sockets')) {
            $this->markTestSkipped('ext-sockets is required for ConnectionManager tests.');
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

    public function test_accept_creates_connection(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $connection = $manager->accept($s1, '127.0.0.1', 12345);

            $this->assertSame('conn-1', $connection->id);
            $this->assertSame('127.0.0.1', $connection->remoteIp);
            $this->assertSame(12345, $connection->remotePort);
            $this->assertSame(1, $manager->getConnectionCount());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }

    public function test_accept_increments_connection_ids(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '127.0.0.1', 12345);
            $conn2 = $manager->accept($s3, '127.0.0.1', 12346);

            $this->assertSame('conn-1', $conn1->id);
            $this->assertSame('conn-2', $conn2->id);
            $this->assertSame(2, $manager->getConnectionCount());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_accept_rejects_when_max_connections_reached(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager(maxConnections: 1);
            $manager->accept($s1, '127.0.0.1', 12345);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Maximum connections (1) reached');
            $manager->accept($s3, '127.0.0.2', 12346);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_accept_rejects_when_max_connections_per_ip_reached(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager(maxConnectionsPerIp: 1);
            $manager->accept($s1, '192.168.1.1', 12345);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Maximum connections per IP (1) reached');
            $manager->accept($s3, '192.168.1.1', 12346);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_get_connection_returns_null_for_unknown_id(): void
    {
        $manager = new ConnectionManager();
        $this->assertNull($manager->getConnection('nonexistent'));
    }

    public function test_get_connection_by_id(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $connection = $manager->accept($s1, '127.0.0.1', 12345);

            $found = $manager->getConnection($connection->id);
            $this->assertNotNull($found);
            $this->assertSame($connection->id, $found->id);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }

    public function test_force_close_removes_connection(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $connection = $manager->accept($s1, '127.0.0.1', 12345);

            $this->assertSame(1, $manager->getConnectionCount());

            $manager->forceClose($connection->id);

            $this->assertSame(0, $manager->getConnectionCount());
            $this->assertNull($manager->getConnection($connection->id));
        } finally {
            @socket_close($s2);
        }
    }

    public function test_force_close_unknown_id_does_nothing(): void
    {
        $manager = new ConnectionManager();
        $manager->forceClose('nonexistent');
        $this->assertSame(0, $manager->getConnectionCount());
    }

    public function test_get_all_connections(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '10.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '10.0.0.2', 2000);

            $all = $manager->getAllConnections();
            $this->assertCount(2, $all);
            $this->assertArrayHasKey($conn1->id, $all);
            $this->assertArrayHasKey($conn2->id, $all);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_get_connections_by_channel(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $conn1 = $manager->accept($s1, '10.0.0.1', 1000);
            $conn2 = $manager->accept($s3, '10.0.0.2', 2000);

            $conn1->subscribe('chat');
            $conn2->subscribe('chat');
            $conn2->subscribe('news');

            $chatConns = $manager->getConnectionsByChannel('chat');
            $this->assertCount(2, $chatConns);

            $newsConns = $manager->getConnectionsByChannel('news');
            $this->assertCount(1, $newsConns);

            $emptyConns = $manager->getConnectionsByChannel('unknown');
            $this->assertCount(0, $emptyConns);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_send_text_to_connection(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager();
            $connection = $manager->accept($s1, '127.0.0.1', 12345);

            $result = $manager->sendText($connection, 'Hello');
            $this->assertTrue($result);
            $this->assertSame(1, $connection->getMessagesSent());
            $this->assertGreaterThan(0, $connection->getBytesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }

    public function test_ip_count_decrements_on_close(): void
    {
        [$s1, $s2] = $this->createSocketPair();
        [$s3, $s4] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager(maxConnectionsPerIp: 1);
            $conn = $manager->accept($s1, '10.0.0.1', 1000);

            $manager->forceClose($conn->id);

            // Should now be able to accept another connection from the same IP
            $conn2 = $manager->accept($s3, '10.0.0.1', 1001);
            $this->assertNotNull($conn2);
        } finally {
            @socket_close($s2);
            @socket_close($s3);
            @socket_close($s4);
        }
    }

    public function test_heartbeat_returns_dead_connections(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager(heartbeatInterval: 1);
            $connection = $manager->accept($s1, '127.0.0.1', 12345);

            // Simulate that the connection's last activity was 3 seconds ago
            // (which exceeds 2x heartbeat interval of 1s = 2s)
            $reflection = new \ReflectionClass($connection);
            $prop = $reflection->getProperty('lastActivityAt');
            $prop->setValue($connection, microtime(true) - 3.0);

            $dead = $manager->heartbeat();

            $this->assertContains($connection->id, $dead);
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }

    public function test_heartbeat_sends_ping_to_idle_connections(): void
    {
        [$s1, $s2] = $this->createSocketPair();

        try {
            $manager = new ConnectionManager(heartbeatInterval: 10);
            $connection = $manager->accept($s1, '127.0.0.1', 12345);

            // Set last activity to 15 seconds ago (exceeds heartbeat but not 2x)
            $reflection = new \ReflectionClass($connection);
            $prop = $reflection->getProperty('lastActivityAt');
            $prop->setValue($connection, microtime(true) - 15.0);

            $dead = $manager->heartbeat();

            // Should not be dead (not > 2x heartbeat = 20s)
            $this->assertNotContains($connection->id, $dead);

            // But a ping should have been sent (bytes sent > 0)
            $this->assertGreaterThan(0, $connection->getBytesSent());
        } finally {
            @socket_close($s1);
            @socket_close($s2);
        }
    }
}
