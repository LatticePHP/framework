<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Module;

#[Module(
    imports: ['NonExistent\\Module'],
)]
class UnresolvedImportModule
{
}
