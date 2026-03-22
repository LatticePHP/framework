<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures;

use Lattice\Module\Attribute\Module;

#[Module(
    controllers: [LifecycleTestController::class],
)]
final class LifecycleTestModule
{
}
