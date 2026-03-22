<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting;

/**
 * Contract for broadcast transport drivers.
 *
 * Implementations handle the actual delivery of broadcast messages
 * to the underlying transport (Redis pub/sub, WebSocket server, log, etc.).
 */
interface BroadcastDriverInterface
{
    /**
     * Broadcast an event to the given channel(s).
     *
     * @param string|array<string> $channels
     * @param string               $event
     * @param array<string, mixed> $data
     */
    public function broadcast(string|array $channels, string $event, array $data): void;
}
