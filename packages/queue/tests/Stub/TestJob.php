<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Stub;

use Lattice\Queue\AbstractJob;

final class TestJob extends AbstractJob
{
    public bool $handled = false;

    public function __construct(
        public readonly string $data = 'test-data',
    ) {
    }

    public function handle(): void
    {
        $this->handled = true;
    }
}
