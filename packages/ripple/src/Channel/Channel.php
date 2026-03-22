<?php

declare(strict_types=1);

namespace Lattice\Ripple\Channel;

/**
 * Base channel tracking subscriber connection IDs.
 */
class Channel
{
    /** @var array<string, true> Connection ID => true */
    protected array $subscribers = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function subscribe(string $connectionId): void
    {
        $this->subscribers[$connectionId] = true;
    }

    public function unsubscribe(string $connectionId): void
    {
        unset($this->subscribers[$connectionId]);
    }

    public function hasSubscriber(string $connectionId): bool
    {
        return isset($this->subscribers[$connectionId]);
    }

    /**
     * @return array<string>
     */
    public function getSubscribers(): array
    {
        return array_keys($this->subscribers);
    }

    public function getSubscriberCount(): int
    {
        return count($this->subscribers);
    }

    public function isEmpty(): bool
    {
        return empty($this->subscribers);
    }

    public function getType(): string
    {
        if (str_starts_with($this->name, 'private-')) {
            return 'private';
        }

        if (str_starts_with($this->name, 'presence-')) {
            return 'presence';
        }

        return 'public';
    }
}
