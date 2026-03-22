<?php

declare(strict_types=1);

namespace Lattice\Compiler\Exceptions;

final class CircularDependencyException extends \RuntimeException
{
    /**
     * @param array<string> $cycle The module class names forming the cycle
     */
    public function __construct(array $cycle)
    {
        $path = implode(' -> ', $cycle);
        parent::__construct("Circular dependency detected: {$path}");
    }
}
