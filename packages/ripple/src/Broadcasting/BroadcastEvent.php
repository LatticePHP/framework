<?php

declare(strict_types=1);

namespace Lattice\Ripple\Broadcasting;

/**
 * Interface for events that can be broadcast to WebSocket channels.
 *
 * Events implementing this interface will be automatically picked up
 * and sent through the Ripple broadcaster to connected clients.
 */
interface BroadcastEvent
{
    /**
     * Get the channel(s) the event should broadcast on.
     *
     * @return string|array<string>
     */
    public function broadcastOn(): string|array;

    /**
     * Get the broadcast event name.
     *
     * Defaults to the class name if not overridden.
     */
    public function broadcastAs(): string;

    /**
     * Get the data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
