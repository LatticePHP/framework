<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\Activity;

#[Activity]
final class FailingActivity
{
    private int $callCount = 0;

    public function failOnce(): string
    {
        $this->callCount++;
        if ($this->callCount === 1) {
            throw new \RuntimeException('Transient failure');
        }
        return 'success_after_retry';
    }

    public function alwaysFail(): never
    {
        throw new \RuntimeException('Permanent failure');
    }

    public function failWithNonRetryable(): never
    {
        throw new \InvalidArgumentException('Bad input');
    }
}
