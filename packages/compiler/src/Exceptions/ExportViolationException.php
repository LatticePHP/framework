<?php

declare(strict_types=1);

namespace Lattice\Compiler\Exceptions;

final class ExportViolationException extends \RuntimeException
{
    public function __construct(string $module, string $exportedClass)
    {
        parent::__construct(
            "Module '{$module}' exports '{$exportedClass}' which is not listed in its providers."
        );
    }
}
