<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Stub;

use Lattice\Queue\AbstractJob;

final class FailingJob extends AbstractJob
{
    public function __construct(
        public readonly string $message = 'Job failed intentionally',
    ) {
    }

    public function handle(): void
    {
        throw new \RuntimeException($this->message);
    }
}
