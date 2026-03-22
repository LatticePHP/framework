<?php

declare(strict_types=1);

namespace Lattice\Events;

final class EventDispatcher
{
    /** @var array<string, array<int, array<int, callable>>> */
    private array $listeners = [];

    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $eventName ??= $event::class;

        if (!isset($this->listeners[$eventName])) {
            return $event;
        }

        foreach ($this->getSortedListeners($eventName) as $listener) {
            if ($event instanceof StoppableEvent && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }

        return $event;
    }

    public function forget(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    public function subscribe(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->listen($eventName, [$subscriber, $params]);
            } elseif (is_array($params)) {
                $method = $params[0];
                $priority = $params[1] ?? 0;
                $this->listen($eventName, [$subscriber, $method], $priority);
            }
        }
    }

    /**
     * @return callable[]
     */
    private function getSortedListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName];
        krsort($listeners);

        $sorted = [];
        foreach ($listeners as $priorityListeners) {
            foreach ($priorityListeners as $listener) {
                $sorted[] = $listener;
            }
        }

        return $sorted;
    }
}
