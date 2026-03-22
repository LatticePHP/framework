<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Module\Attribute\Module;

#[Module(
    controllers: [TestController::class],
)]
final class TestModule
{
}
