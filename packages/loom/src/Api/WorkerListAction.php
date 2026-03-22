<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;

/**
 * GET /api/loom/workers - Active workers list.
 *
 * Returns workers sorted: active first, then by last heartbeat descending.
 */
final class WorkerListAction
{
    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    public function __invoke(Request $request): Response
    {
        $workers = $this->store->getWorkers();

        $data = [];
        foreach ($workers as $workerId => $worker) {
            $uptime = $worker['last_heartbeat'] - $worker['started_at'];

            $data[] = [
                'id' => $workerId,
                'queue' => $worker['queue'],
                'status' => $worker['status'],
                'pid' => $worker['pid'],
                'memory_mb' => $worker['memory_mb'],
                'uptime' => $uptime,
                'jobs_processed' => $worker['jobs_processed'],
                'last_heartbeat' => $worker['last_heartbeat'],
            ];
        }

        return Response::json(['data' => $data]);
    }
}
