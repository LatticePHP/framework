<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

use Lattice\Mcp\Registry\PromptDefinition;

interface PromptRendererInterface
{
    /**
     * Render a prompt and return the MCP result.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function render(PromptDefinition $definition, array $arguments): array;
}
