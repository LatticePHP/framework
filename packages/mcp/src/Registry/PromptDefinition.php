<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

final class PromptDefinition
{
    /**
     * @param list<PromptArgumentDefinition> $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $arguments,
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
            'arguments' => array_map(
                static fn(PromptArgumentDefinition $a): array => $a->toArray(),
                $this->arguments,
            ),
        ];
    }
}
