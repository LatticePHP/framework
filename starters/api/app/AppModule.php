<?php

declare(strict_types=1);

namespace App;

use Lattice\Compiler\Attributes\Module;
use App\Http\HealthController;
use App\Http\UserController;

#[Module(
    imports: [],
    providers: [],
    controllers: [HealthController::class, UserController::class],
    exports: [],
)]
final class AppModule {}
