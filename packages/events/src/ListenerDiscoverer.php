<?php

declare(strict_types=1);

namespace Lattice\Events;

use Lattice\Events\Attributes\Listener;

/**
 * Discovers #[Listener] attributes from classes and auto-registers
 * them with the event dispatcher.
 */
final class ListenerDiscoverer
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
    ) {}

    /**
     * Discover and register all #[Listener] attributes on the given object.
     *
     * @param object $target The object to scan for listener attributes
     * @return int Number of listeners registered
     */
    public function discover(object $target): int
    {
        $ref = new \ReflectionClass($target);
        $count = 0;

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Listener::class);

            foreach ($attributes as $attribute) {
                $listener = $attribute->newInstance();
                $this->dispatcher->listen(
                    $listener->event,
                    [$target, $method->getName()],
                    $listener->priority,
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Discover listeners from multiple objects.
     *
     * @param iterable<object> $targets
     * @return int Total number of listeners registered
     */
    public function discoverAll(iterable $targets): int
    {
        $total = 0;

        foreach ($targets as $target) {
            $total += $this->discover($target);
        }

        return $total;
    }
}
