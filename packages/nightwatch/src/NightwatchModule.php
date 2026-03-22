<?php

declare(strict_types=1);

namespace Lattice\Nightwatch;

use Lattice\Module\Attribute\Module;
use Lattice\Nightwatch\Config\NightwatchConfig;
use Lattice\Nightwatch\Storage\StorageManager;
use Lattice\Nightwatch\Storage\RetentionManager;

#[Module(
    providers: [
        NightwatchServiceProvider::class,
    ],
    exports: [
        NightwatchConfig::class,
        StorageManager::class,
        RetentionManager::class,
    ],
)]
final class NightwatchModule
{
}
