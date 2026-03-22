<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

use Lattice\Mcp\Registry\ToolDefinition;

interface ToolExecutorInterface
{
    /**
     * Execute a tool and return the MCP result.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(ToolDefinition $definition, array $arguments): array;
}
