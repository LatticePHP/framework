<?php

declare(strict_types=1);

namespace Lattice\Queue\Illuminate;

use Lattice\Queue\JobInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Wraps a Lattice JobInterface so it can be dispatched through Illuminate's queue.
 *
 * This adapter makes Lattice jobs compatible with Illuminate's queue system
 * by implementing ShouldQueue and delegating handle() to the inner Lattice job.
 */
final class IlluminateJobWrapper implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    public int $timeout;
    /** @var int[] */
    public array $backoff;

    public function __construct(
        private readonly JobInterface $innerJob,
    ) {
        $this->queue = $innerJob->getQueue();
        $this->connection = $innerJob->getConnection();
        $this->tries = $innerJob->getMaxAttempts();
        $this->timeout = $innerJob->getTimeout();
        $this->backoff = $innerJob->getBackoff();
    }

    public function handle(): void
    {
        $this->innerJob->handle();
    }

    public function getInnerJob(): JobInterface
    {
        return $this->innerJob;
    }
}
