<?php

declare(strict_types=1);

namespace Lattice\Workflow\Compensation;

use RuntimeException;

final class CompensationException extends RuntimeException
{
    /**
     * @param list<\Throwable> $failures
     */
    public function __construct(
        string $message,
        private readonly array $failures,
    ) {
        parent::__construct($message);
    }

    /** @return list<\Throwable> */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
