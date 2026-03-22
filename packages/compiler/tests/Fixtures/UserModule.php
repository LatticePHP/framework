<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Module;

#[Module(
    providers: [UserService::class],
    controllers: [UserController::class],
    exports: [UserService::class],
)]
class UserModule
{
}
