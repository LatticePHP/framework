<?php

declare(strict_types=1);

namespace App\Modules\Notes;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [NoteService::class],
    controllers: [NoteController::class],
    exports: [NoteService::class],
)]
final class NotesModule
{
}
