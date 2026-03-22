<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [ContactService::class],
    controllers: [ContactController::class],
    exports: [ContactService::class],
)]
final class ContactsModule
{
}
