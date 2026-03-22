<?php

declare(strict_types=1);

namespace Lattice\Ai;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [AiServiceProvider::class],
    exports: [AiManager::class],
)]
final class AiModule
{
}
