<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting\Drivers;

use Lattice\Events\Broadcasting\BroadcastDriverInterface;

/**
 * Broadcast driver that silently discards all broadcasts.
 *
 * Intended for testing environments where broadcast side-effects
 * are not desired but the interface must be satisfied.
 */
final class NullBroadcastDriver implements BroadcastDriverInterface
{
    public function broadcast(string|array $channels, string $event, array $data): void
    {
        // Intentionally empty — discard all broadcasts.
    }
}
