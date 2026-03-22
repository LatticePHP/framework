<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [CompanyService::class],
    controllers: [CompanyController::class],
    exports: [CompanyService::class],
)]
final class CompaniesModule
{
}
