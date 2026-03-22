<?php

declare(strict_types=1);

namespace Lattice\Contracts\Module;

interface ModuleDefinitionInterface
{
    /** @return array<class-string> */
    public function getImports(): array;

    /** @return array<class-string> */
    public function getProviders(): array;

    /** @return array<class-string> */
    public function getControllers(): array;

    /** @return array<class-string> */
    public function getExports(): array;
}
