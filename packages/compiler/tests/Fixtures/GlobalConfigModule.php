<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Module;
use Lattice\Compiler\Attributes\GlobalModule;

#[Module(
    providers: [ConfigService::class],
    exports: [ConfigService::class],
)]
#[GlobalModule]
class GlobalConfigModule
{
}
