<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;
use Lattice\Queue\Dispatcher;
use Lattice\Queue\Failed\FailedJobStoreInterface;

/**
 * POST /api/loom/jobs/:id/retry - Retry a single failed job.
 */
final class RetryJobAction
{
    public function __construct(
        private readonly MetricsStore $store,
        private readonly FailedJobStoreInterface $failedJobStore,
        private readonly Dispatcher $dispatcher,
    ) {}

    public function __invoke(Request $request): Response
    {
        $jobId = $request->getParam('id');

        if ($jobId === null) {
            return Response::error('Job ID is required', 400);
        }

        $failedJob = $this->failedJobStore->find($jobId);

        if ($failedJob === null) {
            return Response::error('Failed job not found', 404);
        }

        // Re-dispatch by deserializing the payload
        $job = unserialize($failedJob->payload);

        if ($job instanceof \Lattice\Queue\JobInterface) {
            $this->dispatcher->dispatch($job);
        }

        // Remove from failed store and mark as retried in metrics
        $this->failedJobStore->retry($jobId);
        $this->store->markJobRetried($jobId);

        return Response::json([
            'status' => 'retried',
            'job_id' => $jobId,
        ]);
    }
}
