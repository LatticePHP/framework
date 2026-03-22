<?php

declare(strict_types=1);

namespace Lattice\Ripple\Broadcasting;

use Lattice\Ripple\Server\ConnectionManager;

/**
 * Broadcasts messages to WebSocket channels and connections.
 *
 * Provides methods to send to all subscribers of a channel, to all
 * connected clients, to specific connections, and to channels with
 * exclusions.
 */
final class Broadcaster
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
    ) {}

    /**
     * Broadcast an event to all subscribers of a channel.
     *
     * @param array<string, mixed> $data
     */
    public function broadcastToChannel(string $channelName, string $eventName, array $data): void
    {
        $message = $this->buildMessage($eventName, $channelName, $data);
        $connections = $this->connectionManager->getConnectionsByChannel($channelName);

        foreach ($connections as $connection) {
            if (!$this->connectionManager->sendText($connection, $message)) {
                $this->connectionManager->forceClose($connection->id);
            }
        }
    }

    /**
     * Broadcast an event to all connected clients.
     *
     * @param array<string, mixed> $data
     */
    public function broadcastToAll(string $eventName, array $data): void
    {
        $message = $this->buildMessage($eventName, null, $data);

        foreach ($this->connectionManager->getAllConnections() as $connection) {
            if (!$this->connectionManager->sendText($connection, $message)) {
                $this->connectionManager->forceClose($connection->id);
            }
        }
    }

    /**
     * Broadcast an event to a specific connection.
     *
     * @param array<string, mixed> $data
     */
    public function broadcastToConnection(string $connectionId, string $eventName, array $data): void
    {
        $connection = $this->connectionManager->getConnection($connectionId);

        if ($connection === null) {
            return;
        }

        $message = $this->buildMessage($eventName, null, $data);

        if (!$this->connectionManager->sendText($connection, $message)) {
            $this->connectionManager->forceClose($connection->id);
        }
    }

    /**
     * Broadcast an event to all subscribers of a channel except one.
     *
     * @param array<string, mixed> $data
     */
    public function broadcastToChannelExcept(
        string $channelName,
        string $eventName,
        array $data,
        string $excludeConnectionId,
    ): void {
        $message = $this->buildMessage($eventName, $channelName, $data);
        $connections = $this->connectionManager->getConnectionsByChannel($channelName);

        foreach ($connections as $id => $connection) {
            if ($id === $excludeConnectionId) {
                continue;
            }

            if (!$this->connectionManager->sendText($connection, $message)) {
                $this->connectionManager->forceClose($connection->id);
            }
        }
    }

    /**
     * Broadcast an event to a specific set of connections.
     *
     * @param array<string> $connectionIds
     * @param array<string, mixed> $data
     */
    public function broadcastToConnections(array $connectionIds, string $eventName, array $data): void
    {
        $message = $this->buildMessage($eventName, null, $data);

        foreach ($connectionIds as $connectionId) {
            $connection = $this->connectionManager->getConnection($connectionId);

            if ($connection === null) {
                continue;
            }

            if (!$this->connectionManager->sendText($connection, $message)) {
                $this->connectionManager->forceClose($connection->id);
            }
        }
    }

    /**
     * Build a JSON message envelope.
     *
     * @param array<string, mixed> $data
     */
    private function buildMessage(string $event, ?string $channel, array $data): string
    {
        $message = ['event' => $event, 'data' => $data];

        if ($channel !== null) {
            $message['channel'] = $channel;
        }

        return json_encode($message, JSON_THROW_ON_ERROR);
    }
}
