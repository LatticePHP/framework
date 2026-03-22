<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

use Lattice\Mcp\Registry\ResourceDefinition;

interface ResourceReaderInterface
{
    /**
     * Read a resource and return the MCP result.
     *
     * @param array<string, string> $variables
     * @return array<string, mixed>
     */
    public function read(ResourceDefinition $definition, array $variables): array;
}
