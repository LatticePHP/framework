<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting\Attributes;

use Attribute;

/**
 * Marks an event class as broadcastable on the given channel(s).
 *
 * When the compiler processes this attribute, the event will be
 * automatically wired for broadcasting on the specified channels.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Broadcastable
{
    /** @var array<string> */
    public readonly array $channels;

    /**
     * @param string|array<string> $channel One or more channel names/patterns.
     */
    public function __construct(
        string|array $channel,
    ) {
        $this->channels = is_array($channel) ? $channel : [$channel];
    }
}
