<?php

declare(strict_types=1);

namespace App;

use App\Modules\Activities\ActivitiesModule;
use App\Modules\Auth\AuthModule;
use App\Modules\Companies\CompaniesModule;
use App\Modules\Contacts\ContactsModule;
use App\Modules\Dashboard\DashboardModule;
use App\Modules\Deals\DealsModule;
use App\Modules\Notes\NotesModule;
use Lattice\Module\Attribute\Module;

/**
 * Root application module.
 *
 * Imports all feature modules that comprise the CRM application.
 * This is the single entry point registered with Application::configure()->withModules().
 */
#[Module(
    imports: [
        AuthModule::class,
        ContactsModule::class,
        CompaniesModule::class,
        DealsModule::class,
        ActivitiesModule::class,
        NotesModule::class,
        DashboardModule::class,
    ],
)]
final class AppModule
{
}
