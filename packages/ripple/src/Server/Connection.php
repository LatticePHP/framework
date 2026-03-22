<?php

declare(strict_types=1);

namespace Lattice\Ripple\Server;

use Socket;

/**
 * Represents a single WebSocket connection.
 *
 * Tracks the socket resource, metadata, subscribed channels, authentication
 * state, and traffic statistics.
 */
final class Connection
{
    private float $lastActivityAt;

    /** @var array<string, true> */
    private array $channels = [];

    private bool $authenticated = false;

    /** @var array<string, mixed>|null */
    private ?array $authData = null;

    private int $bytesSent = 0;
    private int $bytesReceived = 0;
    private int $messagesSent = 0;
    private int $messagesReceived = 0;

    private string $buffer = '';

    public function __construct(
        public readonly string $id,
        public readonly Socket $socket,
        public readonly string $remoteIp,
        public readonly int $remotePort,
        public readonly float $connectedAt,
    ) {
        $this->lastActivityAt = $connectedAt;
    }

    public function getLastActivityAt(): float
    {
        return $this->lastActivityAt;
    }

    public function updateLastActivity(): void
    {
        $this->lastActivityAt = microtime(true);
    }

    /**
     * @return array<string>
     */
    public function getChannels(): array
    {
        return array_keys($this->channels);
    }

    public function isSubscribedTo(string $channel): bool
    {
        return isset($this->channels[$channel]);
    }

    public function subscribe(string $channel): void
    {
        $this->channels[$channel] = true;
    }

    public function unsubscribe(string $channel): void
    {
        unset($this->channels[$channel]);
    }

    public function unsubscribeAll(): void
    {
        $this->channels = [];
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function setAuthenticated(bool $authenticated, ?array $data = null): void
    {
        $this->authenticated = $authenticated;
        $this->authData = $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAuthData(): ?array
    {
        return $this->authData;
    }

    public function addBytesSent(int $bytes): void
    {
        $this->bytesSent += $bytes;
    }

    public function addBytesReceived(int $bytes): void
    {
        $this->bytesReceived += $bytes;
    }

    public function incrementMessagesSent(): void
    {
        $this->messagesSent++;
    }

    public function incrementMessagesReceived(): void
    {
        $this->messagesReceived++;
    }

    public function getBytesSent(): int
    {
        return $this->bytesSent;
    }

    public function getBytesReceived(): int
    {
        return $this->bytesReceived;
    }

    public function getMessagesSent(): int
    {
        return $this->messagesSent;
    }

    public function getMessagesReceived(): int
    {
        return $this->messagesReceived;
    }

    public function appendToBuffer(string $data): void
    {
        $this->buffer .= $data;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function consumeBuffer(int $bytes): void
    {
        $this->buffer = substr($this->buffer, $bytes);
    }

    public function clearBuffer(): void
    {
        $this->buffer = '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'remote_ip' => $this->remoteIp,
            'remote_port' => $this->remotePort,
            'connected_at' => $this->connectedAt,
            'last_activity_at' => $this->lastActivityAt,
            'channels' => $this->getChannels(),
            'authenticated' => $this->authenticated,
            'bytes_sent' => $this->bytesSent,
            'bytes_received' => $this->bytesReceived,
            'messages_sent' => $this->messagesSent,
            'messages_received' => $this->messagesReceived,
        ];
    }
}
