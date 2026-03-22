<?php

declare(strict_types=1);

namespace Lattice\Events;

final class AsyncEventDispatcher
{
    /** @var array<int, array{object, string|null}> */
    private array $queue = [];

    public function __construct(
        private readonly EventDispatcher $dispatcher,
    ) {}

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->queue[] = [$event, $eventName];

        return $event;
    }

    public function flush(): void
    {
        $queue = $this->queue;
        $this->queue = [];

        foreach ($queue as [$event, $eventName]) {
            $this->dispatcher->dispatch($event, $eventName);
        }
    }
}
