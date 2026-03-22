<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp;

interface McpToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return array<string, mixed> JSON Schema for tool input
     */
    public function getInputSchema(): array;

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>|string
     */
    public function execute(array $arguments): array|string;
}
