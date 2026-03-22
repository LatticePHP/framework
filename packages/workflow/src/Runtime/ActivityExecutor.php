<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

use Lattice\Contracts\Workflow\RetryPolicyInterface;
use Lattice\Workflow\RetryPolicy;

abstract class ActivityExecutor
{
    /**
     * Execute an activity with retry logic.
     */
    public function execute(
        string $activityClass,
        string $method,
        array $args,
        ?RetryPolicyInterface $retryPolicy = null,
    ): mixed {
        $retryPolicy ??= new RetryPolicy();
        $attempt = 0;
        $lastException = null;

        while ($attempt < $retryPolicy->getMaxAttempts()) {
            $attempt++;

            try {
                return $this->doExecute($activityClass, $method, $args, $attempt);
            } catch (\Throwable $e) {
                $lastException = $e;

                // Check if exception is non-retryable
                foreach ($retryPolicy->getNonRetryableExceptions() as $nonRetryable) {
                    if ($e instanceof $nonRetryable) {
                        throw $e;
                    }
                }

                if ($attempt >= $retryPolicy->getMaxAttempts()) {
                    break;
                }

                // Calculate backoff delay
                $delay = $this->calculateDelay($retryPolicy, $attempt);
                $this->waitBeforeRetry($delay);
            }
        }

        throw $lastException;
    }

    /**
     * Actually execute the activity. Subclasses implement this.
     */
    abstract protected function doExecute(
        string $activityClass,
        string $method,
        array $args,
        int $attempt,
    ): mixed;

    /**
     * Wait before retrying. Can be overridden for testing.
     */
    protected function waitBeforeRetry(int $seconds): void
    {
        // Default no-op for sync executors; real executor would sleep
    }

    private function calculateDelay(RetryPolicyInterface $retryPolicy, int $attempt): int
    {
        $delay = (int) ($retryPolicy->getInitialInterval() * ($retryPolicy->getBackoffCoefficient() ** ($attempt - 1)));

        $maxInterval = $retryPolicy->getMaxInterval();
        if ($maxInterval !== null && $delay > $maxInterval) {
            $delay = $maxInterval;
        }

        return $delay;
    }
}
