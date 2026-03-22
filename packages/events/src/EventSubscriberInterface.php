<?php

declare(strict_types=1);

namespace Lattice\Events;

interface EventSubscriberInterface
{
    /** @return array<string, string|array> event name => method name(s) */
    public static function getSubscribedEvents(): array;
}
