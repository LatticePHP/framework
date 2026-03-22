<?php

declare(strict_types=1);

namespace Lattice\Module\Exception;

use RuntimeException;

final class ModuleNotFoundException extends RuntimeException
{
    public static function forClass(string $className): self
    {
        return new self(sprintf('Module "%s" is not registered.', $className));
    }
}
