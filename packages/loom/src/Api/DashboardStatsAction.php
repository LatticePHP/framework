<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;

/**
 * GET /api/loom/stats - Dashboard overview stats.
 *
 * Supports ?period=1h|6h|24h|7d for time window.
 */
final class DashboardStatsAction
{
    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    public function __invoke(Request $request): Response
    {
        $period = $request->getQuery('period') ?? '1h';

        $allowedPeriods = ['5m', '1h', '6h', '24h', '7d'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = '1h';
        }

        $snapshot = $this->store->getSnapshot($period);

        return Response::json($snapshot->toArray());
    }
}
