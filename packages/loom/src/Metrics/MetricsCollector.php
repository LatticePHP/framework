<?php

declare(strict_types=1);

namespace Lattice\Loom\Metrics;

use Lattice\Events\EventDispatcher;
use Lattice\Events\EventSubscriberInterface;
use Lattice\Loom\Events\JobDispatched;
use Lattice\Loom\Events\JobFailed;
use Lattice\Loom\Events\JobProcessed;

/**
 * Listens to queue job lifecycle events and records metrics
 * into the MetricsStore for dashboard consumption.
 */
final class MetricsCollector implements EventSubscriberInterface
{
    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            JobDispatched::class => 'onJobDispatched',
            JobProcessed::class => 'onJobProcessed',
            JobFailed::class => 'onJobFailed',
        ];
    }

    public function register(EventDispatcher $dispatcher): void
    {
        $dispatcher->subscribe($this);
    }

    public function onJobDispatched(JobDispatched $event): void
    {
        $this->store->recordJobDispatched(
            $event->jobId,
            $event->className,
            $event->queue,
            $event->timestamp,
        );
    }

    public function onJobProcessed(JobProcessed $event): void
    {
        $this->store->recordJobProcessed(
            $event->jobId,
            $event->className,
            $event->queue,
            $event->runtimeMs,
            $event->timestamp,
        );
    }

    public function onJobFailed(JobFailed $event): void
    {
        $this->store->recordJobFailed(
            $event->jobId,
            $event->className,
            $event->queue,
            $event->exceptionClass,
            $event->exceptionMessage,
            $event->attemptCount,
            $event->timestamp,
        );
    }
}
