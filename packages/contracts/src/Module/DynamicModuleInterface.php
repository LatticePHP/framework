<?php

declare(strict_types=1);

namespace Lattice\Contracts\Module;

interface DynamicModuleInterface
{
    public static function register(mixed ...$options): ModuleDefinitionInterface;
}
