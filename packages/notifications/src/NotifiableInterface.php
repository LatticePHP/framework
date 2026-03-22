<?php

declare(strict_types=1);

namespace Lattice\Notifications;

interface NotifiableInterface
{
    /**
     * Get the notification routing information for the given channel.
     */
    public function routeNotificationFor(string $channel): mixed;
}
