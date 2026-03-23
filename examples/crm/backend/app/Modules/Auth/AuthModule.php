<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use Lattice\Auth\AuthModule as FrameworkAuthModule;
use Lattice\Auth\Http\WorkspaceController;
use Lattice\Module\Attribute\Module;

/**
 * Auth module: imports the framework's AuthModule which provides
 * login/register/refresh/me endpoints and JWT guard.
 * Also registers the WorkspaceController for workspace CRUD and invitations.
 */
#[Module(
    imports: [FrameworkAuthModule::class],
    controllers: [WorkspaceController::class],
)]
final class AuthModule
{
}
