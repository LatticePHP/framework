<?php

declare(strict_types=1);

namespace Lattice\Core\CircuitBreaker;

use RuntimeException;

final class CircuitOpenException extends RuntimeException
{
    public function __construct(string $service)
    {
        parent::__construct("Circuit '{$service}' is open");
    }
}
