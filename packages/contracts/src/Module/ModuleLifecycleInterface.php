<?php

declare(strict_types=1);

namespace Lattice\Contracts\Module;

interface ModuleLifecycleInterface
{
    public function onModuleInit(): void;

    public function onModuleDestroy(): void;
}
