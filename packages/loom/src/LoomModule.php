<?php

declare(strict_types=1);

namespace Lattice\Loom;

use Lattice\Loom\Api\DashboardStatsAction;
use Lattice\Loom\Api\EventStreamAction;
use Lattice\Loom\Api\FailedJobsAction;
use Lattice\Loom\Api\JobDetailAction;
use Lattice\Loom\Api\QueueMetricsAction;
use Lattice\Loom\Api\RecentJobsAction;
use Lattice\Loom\Api\RetryAllAction;
use Lattice\Loom\Api\RetryJobAction;
use Lattice\Loom\Api\WorkerListAction;
use Lattice\Module\Attribute\Module;

#[Module(
    providers: [LoomServiceProvider::class],
    controllers: [
        DashboardStatsAction::class,
        RecentJobsAction::class,
        FailedJobsAction::class,
        JobDetailAction::class,
        RetryJobAction::class,
        RetryAllAction::class,
        QueueMetricsAction::class,
        WorkerListAction::class,
        EventStreamAction::class,
    ],
)]
final class LoomModule
{
    /**
     * Returns the route definitions for the Loom API.
     *
     * @return array<int, array{method: string, path: string, action: class-string}>
     */
    public static function routes(string $prefix = '/api/loom'): array
    {
        return [
            ['method' => 'GET', 'path' => $prefix . '/stats', 'action' => DashboardStatsAction::class],
            ['method' => 'GET', 'path' => $prefix . '/jobs/recent', 'action' => RecentJobsAction::class],
            ['method' => 'GET', 'path' => $prefix . '/jobs/failed', 'action' => FailedJobsAction::class],
            ['method' => 'GET', 'path' => $prefix . '/jobs/retry-all', 'action' => RetryAllAction::class],
            ['method' => 'GET', 'path' => $prefix . '/jobs/:id', 'action' => JobDetailAction::class],
            ['method' => 'POST', 'path' => $prefix . '/jobs/:id/retry', 'action' => RetryJobAction::class],
            ['method' => 'POST', 'path' => $prefix . '/jobs/retry-all', 'action' => RetryAllAction::class],
            ['method' => 'GET', 'path' => $prefix . '/metrics', 'action' => QueueMetricsAction::class],
            ['method' => 'GET', 'path' => $prefix . '/workers', 'action' => WorkerListAction::class],
            ['method' => 'GET', 'path' => $prefix . '/events', 'action' => EventStreamAction::class],
        ];
    }
}
