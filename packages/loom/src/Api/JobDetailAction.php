<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;

/**
 * GET /api/loom/jobs/:id - Full job detail.
 */
final class JobDetailAction
{
    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    public function __invoke(Request $request): Response
    {
        $jobId = $request->getParam('id');

        if ($jobId === null) {
            return Response::error('Job ID is required', 400);
        }

        $job = $this->store->findJob($jobId);

        if ($job === null) {
            return Response::error('Job not found', 404);
        }

        return Response::json(['data' => $job]);
    }
}
