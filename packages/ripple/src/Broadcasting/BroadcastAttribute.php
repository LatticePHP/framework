<?php

declare(strict_types=1);

namespace Lattice\Ripple\Broadcasting;

use Attribute;

/**
 * Marks an event class for broadcasting to WebSocket channels.
 *
 * Supports {property} interpolation in the channel name, resolved
 * against the event instance's public properties at broadcast time.
 *
 * Example:
 *   #[Broadcast(channel: 'orders.{orderId}', as: 'order.updated')]
 *   final class OrderUpdated { public int $orderId = 42; }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class BroadcastAttribute
{
    /** @var array<string> */
    public readonly array $channels;

    /**
     * @param string|array<string> $channel One or more channel name patterns.
     * @param string|null $as Custom event name (defaults to class basename).
     */
    public function __construct(
        string|array $channel,
        public readonly ?string $as = null,
    ) {
        $this->channels = is_array($channel) ? $channel : [$channel];
    }

    /**
     * Resolve channel names by interpolating {property} placeholders.
     *
     * @return array<string>
     */
    public function resolveChannels(object $event): array
    {
        $channels = [];

        foreach ($this->channels as $pattern) {
            $channels[] = (string) preg_replace_callback(
                '/\{(\w+)\}/',
                static function (array $matches) use ($event): string {
                    $property = $matches[1];

                    if (property_exists($event, $property)) {
                        return (string) $event->{$property};
                    }

                    return $matches[0];
                },
                $pattern,
            );
        }

        return $channels;
    }

    /**
     * Resolve the event name.
     */
    public function resolveEventName(object $event): string
    {
        if ($this->as !== null) {
            return $this->as;
        }

        $class = $event::class;
        $parts = explode('\\', $class);

        return end($parts);
    }
}
