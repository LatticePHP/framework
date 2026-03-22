<?php

declare(strict_types=1);

namespace Lattice\Events\Illuminate;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;

final class IlluminateEventDispatcher
{
    private Dispatcher $dispatcher;

    public function __construct(?Container $container = null)
    {
        $this->dispatcher = new Dispatcher($container ?? new Container());
    }

    public function listen(string $event, callable|string $listener): void
    {
        $this->dispatcher->listen($event, $listener);
    }

    public function dispatch(object|string $event, mixed $payload = []): ?array
    {
        return $this->dispatcher->dispatch($event, $payload);
    }

    public function subscribe(string $subscriber): void
    {
        $this->dispatcher->subscribe($subscriber);
    }

    public function forget(string $event): void
    {
        $this->dispatcher->forget($event);
    }

    /**
     * Access the full Illuminate Dispatcher for advanced features:
     * wildcard listeners, queued listeners, event discovery, etc.
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }
}
