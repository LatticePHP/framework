<?php

declare(strict_types=1);

namespace Lattice\Module\Exception;

use RuntimeException;

final class UnsatisfiedImportException extends RuntimeException
{
    public static function forModule(string $module, string $missingImport): self
    {
        return new self(sprintf(
            'Module "%s" imports "%s", which is not registered.',
            $module,
            $missingImport,
        ));
    }
}
