<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting;

/**
 * Interface for events that should be broadcast to external channels.
 *
 * Events implementing this interface will be automatically picked up
 * by the BroadcastInterceptor and dispatched through the configured
 * broadcast driver.
 */
interface ShouldBroadcast
{
    /**
     * Get the channel(s) the event should broadcast on.
     *
     * @return string|array<string>
     */
    public function broadcastOn(): string|array;

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string;

    /**
     * Get the data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
