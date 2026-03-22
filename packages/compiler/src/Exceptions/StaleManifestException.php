<?php

declare(strict_types=1);

namespace Lattice\Compiler\Exceptions;

final class StaleManifestException extends \RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct("Manifest at '{$path}' is stale or invalid and must be recompiled.");
    }
}
