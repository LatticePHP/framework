<?php

declare(strict_types=1);

namespace Lattice\Events\Attributes;

use Attribute;

/**
 * Marks a method as an event listener.
 *
 * When applied to a method on a provider or service class,
 * the event system will auto-discover and register it as a
 * listener for the specified event.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Listener
{
    public function __construct(
        public readonly string $event,
        public readonly int $priority = 0,
    ) {}
}
