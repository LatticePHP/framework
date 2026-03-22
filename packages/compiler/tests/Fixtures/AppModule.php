<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Module;

#[Module(
    imports: [UserModule::class],
    providers: [SimpleService::class],
    controllers: [DefaultController::class],
    exports: [SimpleService::class],
)]
class AppModule
{
}
