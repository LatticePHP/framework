<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

final class TimerPendingException extends \RuntimeException
{
    public function __construct(
        private readonly string $timerId,
    ) {
        parent::__construct("Timer pending: {$timerId}");
    }

    public function getTimerId(): string
    {
        return $this->timerId;
    }
}
