<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;
use Lattice\Queue\Dispatcher;
use Lattice\Queue\Failed\FailedJobStoreInterface;

/**
 * POST /api/loom/jobs/retry-all - Retry all failed jobs.
 */
final class RetryAllAction
{
    public function __construct(
        private readonly MetricsStore $store,
        private readonly FailedJobStoreInterface $failedJobStore,
        private readonly Dispatcher $dispatcher,
    ) {}

    public function __invoke(Request $request): Response
    {
        $failedJobs = $this->failedJobStore->all();
        $count = 0;

        foreach ($failedJobs as $failedJob) {
            $job = unserialize($failedJob->payload);

            if ($job instanceof \Lattice\Queue\JobInterface) {
                $this->dispatcher->dispatch($job);
            }

            $this->failedJobStore->retry($failedJob->id);
            $this->store->markJobRetried($failedJob->id);
            $count++;
        }

        return Response::json([
            'status' => 'retried',
            'count' => $count,
        ]);
    }
}
