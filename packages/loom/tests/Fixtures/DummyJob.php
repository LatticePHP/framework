<?php

declare(strict_types=1);

namespace Lattice\Loom\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

final class DummyJob extends AbstractJob
{
    public function handle(): void
    {
        // no-op: used in tests
    }
}
