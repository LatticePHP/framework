<?php

declare(strict_types=1);

namespace App;

use Lattice\Compiler\Attributes\Module;
use App\Handlers\OrderEventsHandler;
use App\Http\StatusController;

#[Module(
    imports: [],
    providers: [OrderEventsHandler::class],
    controllers: [StatusController::class],
    exports: [],
)]
final class AppModule {}
