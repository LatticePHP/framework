<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\Fixtures;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [TestService::class],
    controllers: [TestController::class],
    exports: [TestService::class],
)]
final class TestModule
{
}
