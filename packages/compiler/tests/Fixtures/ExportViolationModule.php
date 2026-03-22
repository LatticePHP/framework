<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Module;

/**
 * This module exports a class that is not in its providers list,
 * which is an export visibility violation.
 */
#[Module(
    providers: [SimpleService::class],
    exports: [UserService::class],
)]
class ExportViolationModule
{
}
