<?php

declare(strict_types=1);

namespace Lattice\Module\Exception;

use RuntimeException;

final class CircularDependencyException extends RuntimeException
{
    /**
     * @param list<string> $cycle
     */
    public static function forModules(array $cycle): self
    {
        return new self(sprintf(
            'Circular module dependency detected: %s',
            implode(' -> ', $cycle),
        ));
    }
}
