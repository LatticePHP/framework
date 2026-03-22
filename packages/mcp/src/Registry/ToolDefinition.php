<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

final class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly string $className,
        public readonly string $methodName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
