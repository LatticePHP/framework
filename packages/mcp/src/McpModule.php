<?php

declare(strict_types=1);

namespace Lattice\Mcp;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [
        McpServiceProvider::class,
    ],
    exports: [
        McpServiceProvider::class,
    ],
)]
final class McpModule
{
}
