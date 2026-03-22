<?php

declare(strict_types=1);

namespace Lattice\Auth;

use Lattice\Auth\Http\AuthController;
use Lattice\Compiler\Attributes\Module;
use Lattice\Contracts\Auth\TokenIssuerInterface;

#[Module(
    providers: [AuthServiceProvider::class],
    controllers: [AuthController::class],
    exports: [TokenIssuerInterface::class, Hashing\HashManager::class, JwtAuthenticationGuard::class],
)]
final class AuthModule
{
}
