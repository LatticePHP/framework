<?php

declare(strict_types=1);

namespace Lattice\Loom;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Events\EventDispatcher;
use Lattice\Loom\Api\DashboardStatsAction;
use Lattice\Loom\Api\EventStreamAction;
use Lattice\Loom\Api\FailedJobsAction;
use Lattice\Loom\Api\JobDetailAction;
use Lattice\Loom\Api\QueueMetricsAction;
use Lattice\Loom\Api\RecentJobsAction;
use Lattice\Loom\Api\RetryAllAction;
use Lattice\Loom\Api\RetryJobAction;
use Lattice\Loom\Api\WorkerListAction;
use Lattice\Loom\Metrics\MetricsCollector;
use Lattice\Loom\Metrics\MetricsStore;

final class LoomServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Core metrics infrastructure
        $container->singleton(MetricsStore::class, MetricsStore::class);
        $container->singleton(MetricsCollector::class, MetricsCollector::class);
        $container->singleton(LoomAdminGuard::class, LoomAdminGuard::class);

        // API actions
        $container->bind(DashboardStatsAction::class, DashboardStatsAction::class);
        $container->bind(RecentJobsAction::class, RecentJobsAction::class);
        $container->bind(FailedJobsAction::class, FailedJobsAction::class);
        $container->bind(JobDetailAction::class, JobDetailAction::class);
        $container->bind(RetryJobAction::class, RetryJobAction::class);
        $container->bind(RetryAllAction::class, RetryAllAction::class);
        $container->bind(QueueMetricsAction::class, QueueMetricsAction::class);
        $container->bind(WorkerListAction::class, WorkerListAction::class);
        $container->bind(EventStreamAction::class, EventStreamAction::class);
    }

    /**
     * Boot the Loom service: register event listeners.
     */
    public function boot(MetricsCollector $collector, EventDispatcher $dispatcher): void
    {
        $collector->register($dispatcher);
    }
}
