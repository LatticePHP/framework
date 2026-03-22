<?php

declare(strict_types=1);

namespace App\Modules\Activities;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [ActivityService::class],
    controllers: [ActivityController::class],
    exports: [ActivityService::class],
)]
final class ActivitiesModule
{
}
