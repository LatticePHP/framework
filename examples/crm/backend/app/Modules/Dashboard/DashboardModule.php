<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [DashboardService::class],
    controllers: [DashboardController::class],
    exports: [DashboardService::class],
)]
final class DashboardModule
{
}
