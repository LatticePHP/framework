<?php

declare(strict_types=1);

namespace Lattice\Compiler\Exceptions;

final class UnresolvedImportException extends \RuntimeException
{
    public function __construct(string $module, string $unresolvedImport)
    {
        parent::__construct(
            "Module '{$module}' imports '{$unresolvedImport}' which is not registered in the module graph."
        );
    }
}
