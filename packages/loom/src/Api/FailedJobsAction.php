<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;

/**
 * GET /api/loom/jobs/failed - Paginated failed jobs.
 *
 * Supports ?queue=default, ?search=ClassName, ?page=1, ?per_page=25
 */
final class FailedJobsAction
{
    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    public function __invoke(Request $request): Response
    {
        $queue = $request->getQuery('queue');
        $search = $request->getQuery('search');
        $page = max(1, (int) ($request->getQuery('page') ?? '1'));
        $perPage = min(100, max(1, (int) ($request->getQuery('per_page') ?? '25')));

        $offset = ($page - 1) * $perPage;

        $jobs = $this->store->getFailedJobs($queue, $perPage, $offset, $search);

        return Response::json([
            'data' => $jobs,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
