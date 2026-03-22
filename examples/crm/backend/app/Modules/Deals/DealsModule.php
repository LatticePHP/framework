<?php

declare(strict_types=1);

namespace App\Modules\Deals;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [DealService::class],
    controllers: [DealController::class],
    exports: [DealService::class],
)]
final class DealsModule
{
}
