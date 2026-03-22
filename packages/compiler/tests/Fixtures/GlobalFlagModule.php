<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Module;

#[Module(
    providers: [ConfigService::class],
    exports: [ConfigService::class],
    global: true,
)]
class GlobalFlagModule
{
}
