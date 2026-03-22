<?php

declare(strict_types=1);

namespace App\Modules\App;

use Lattice\Auth\AuthModule;
use Lattice\Module\Attribute\Module;

#[Module(
    imports: [AuthModule::class],
)]
final class AppModule {}
