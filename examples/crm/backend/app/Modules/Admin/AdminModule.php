<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use Lattice\Module\Attribute\Module;

/**
 * Admin portal module.
 *
 * Provides a unified dashboard page at /admin that links to all
 * LatticePHP monitoring and management dashboards.
 */
#[Module(
    imports: [],
    controllers: [AdminController::class],
)]
final class AdminModule
{
}
