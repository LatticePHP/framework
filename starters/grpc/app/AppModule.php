<?php

declare(strict_types=1);

namespace App;

use Lattice\Compiler\Attributes\Module;
use App\Services\GreeterService;

#[Module(
    imports: [],
    providers: [GreeterService::class],
    controllers: [],
    exports: [],
)]
final class AppModule {}
