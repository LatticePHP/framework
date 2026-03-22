<?php

declare(strict_types=1);

namespace Lattice\Ripple\Server;

use RuntimeException;
use Socket;

/**
 * Tracks all active WebSocket connections.
 *
 * Provides accept, close, force-close, heartbeat detection, and connection
 * limit enforcement.
 */
final class ConnectionManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    /** @var array<string, int> IP => connection count */
    private array $ipCounts = [];

    private int $nextId = 1;

    public function __construct(
        private readonly int $maxConnections = 10000,
        private readonly int $maxConnectionsPerIp = 100,
        private readonly int $heartbeatInterval = 25,
    ) {}

    /**
     * Accept a new connection from a socket.
     *
     * @throws RuntimeException If connection limits are reached.
     */
    public function accept(Socket $socket, string $remoteIp, int $remotePort): Connection
    {
        if (count($this->connections) >= $this->maxConnections) {
            throw new RuntimeException(
                sprintf('Maximum connections (%d) reached.', $this->maxConnections),
            );
        }

        $ipCount = $this->ipCounts[$remoteIp] ?? 0;
        if ($ipCount >= $this->maxConnectionsPerIp) {
            throw new RuntimeException(
                sprintf(
                    'Maximum connections per IP (%d) reached for %s.',
                    $this->maxConnectionsPerIp,
                    $remoteIp,
                ),
            );
        }

        $id = $this->generateId();

        $connection = new Connection(
            id: $id,
            socket: $socket,
            remoteIp: $remoteIp,
            remotePort: $remotePort,
            connectedAt: microtime(true),
        );

        $this->connections[$id] = $connection;
        $this->ipCounts[$remoteIp] = $ipCount + 1;

        return $connection;
    }

    /**
     * Gracefully close a connection (sends close frame first).
     */
    public function close(string $connectionId, int $code = Frame::CLOSE_NORMAL, string $reason = ''): void
    {
        $connection = $this->getConnection($connectionId);

        if ($connection === null) {
            return;
        }

        $closeFrame = Frame::close($code, $reason);
        $this->sendRaw($connection, $closeFrame);

        $this->forceClose($connectionId);
    }

    /**
     * Force-close a connection without sending a close frame.
     */
    public function forceClose(string $connectionId): void
    {
        $connection = $this->connections[$connectionId] ?? null;

        if ($connection === null) {
            return;
        }

        $this->decrementIpCount($connection->remoteIp);
        unset($this->connections[$connectionId]);

        @socket_close($connection->socket);
    }

    /**
     * Send a raw frame to a connection.
     *
     * @return bool True if send succeeded.
     */
    public function sendRaw(Connection $connection, string $data): bool
    {
        $length = strlen($data);
        $sent = @socket_write($connection->socket, $data, $length);

        if ($sent === false) {
            return false;
        }

        $connection->addBytesSent($sent);

        return true;
    }

    /**
     * Send a text message to a connection.
     */
    public function sendText(Connection $connection, string $message): bool
    {
        $frame = Frame::text($message);
        $result = $this->sendRaw($connection, $frame);

        if ($result) {
            $connection->incrementMessagesSent();
        }

        return $result;
    }

    /**
     * Send ping frames to all connections that have been idle.
     *
     * @return array<string> Connection IDs that appear dead.
     */
    public function heartbeat(): array
    {
        $now = microtime(true);
        $deadConnectionIds = [];
        $pingThreshold = $now - $this->heartbeatInterval;
        $deadThreshold = $now - ($this->heartbeatInterval * 2);

        foreach ($this->connections as $id => $connection) {
            if ($connection->getLastActivityAt() < $deadThreshold) {
                $deadConnectionIds[] = $id;
            } elseif ($connection->getLastActivityAt() < $pingThreshold) {
                $this->sendRaw($connection, Frame::ping());
            }
        }

        return $deadConnectionIds;
    }

    public function getConnection(string $id): ?Connection
    {
        return $this->connections[$id] ?? null;
    }

    /**
     * @return array<string, Connection>
     */
    public function getAllConnections(): array
    {
        return $this->connections;
    }

    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Get all connections subscribed to a given channel.
     *
     * @return array<string, Connection>
     */
    public function getConnectionsByChannel(string $channel): array
    {
        $result = [];

        foreach ($this->connections as $id => $connection) {
            if ($connection->isSubscribedTo($channel)) {
                $result[$id] = $connection;
            }
        }

        return $result;
    }

    public function getMaxConnections(): int
    {
        return $this->maxConnections;
    }

    public function getHeartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    private function generateId(): string
    {
        return 'conn-' . $this->nextId++;
    }

    private function decrementIpCount(string $ip): void
    {
        if (isset($this->ipCounts[$ip])) {
            $this->ipCounts[$ip]--;
            if ($this->ipCounts[$ip] <= 0) {
                unset($this->ipCounts[$ip]);
            }
        }
    }
}
