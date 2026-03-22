<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;

/**
 * GET /api/loom/metrics - Time-series metrics (throughput, runtime).
 *
 * Supports ?period=5m|1h|24h|7d, ?queue=default
 */
final class QueueMetricsAction
{
    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    public function __invoke(Request $request): Response
    {
        $period = $request->getQuery('period') ?? '1h';
        $queue = $request->getQuery('queue');

        $allowedPeriods = ['5m', '1h', '6h', '24h', '7d'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = '1h';
        }

        $metrics = $this->store->getTimeSeriesMetrics($period, $queue);

        return Response::json([
            'data' => $metrics,
            'meta' => [
                'period' => $period,
                'queue' => $queue,
            ],
        ]);
    }
}
