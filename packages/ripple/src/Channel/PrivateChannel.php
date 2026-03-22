<?php

declare(strict_types=1);

namespace Lattice\Ripple\Channel;

/**
 * Private channel that requires authentication before subscribing.
 *
 * Channel names are prefixed with "private-".
 */
final class PrivateChannel extends Channel
{
    /** @var array<string, true> Connection IDs that have been authenticated */
    private array $authenticated = [];

    public function authenticate(string $connectionId): void
    {
        $this->authenticated[$connectionId] = true;
    }

    public function isAuthenticated(string $connectionId): bool
    {
        return isset($this->authenticated[$connectionId]);
    }

    public function unsubscribe(string $connectionId): void
    {
        parent::unsubscribe($connectionId);
        unset($this->authenticated[$connectionId]);
    }
}
