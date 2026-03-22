<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use Lattice\Auth\AuthModule as FrameworkAuthModule;
use Lattice\Module\Attribute\Module;

/**
 * Auth module: imports the framework's AuthModule which provides
 * login/register/refresh/me endpoints and JWT guard.
 */
#[Module(
    imports: [FrameworkAuthModule::class],
)]
final class AuthModule
{
}
