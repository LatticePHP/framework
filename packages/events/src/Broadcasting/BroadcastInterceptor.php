<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting;

/**
 * Interceptor that automatically broadcasts events implementing ShouldBroadcast.
 *
 * This interceptor is designed to be attached to the event dispatcher so that
 * after an event is dispatched locally, it is also pushed through the broadcast
 * driver to external subscribers (WebSocket clients, SSE consumers, etc.).
 */
final class BroadcastInterceptor
{
    public function __construct(
        private readonly BroadcastDriverInterface $driver,
    ) {}

    /**
     * Handle an event — if it implements ShouldBroadcast, broadcast it.
     *
     * This method is intended to be called after normal event dispatch.
     * It is a no-op for events that do not implement ShouldBroadcast.
     */
    public function handle(object $event): void
    {
        if (!$event instanceof ShouldBroadcast) {
            return;
        }

        $this->driver->broadcast(
            $event->broadcastOn(),
            $event->broadcastAs(),
            $event->broadcastWith(),
        );
    }
}
