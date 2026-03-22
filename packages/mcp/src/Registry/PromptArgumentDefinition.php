<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

final class PromptArgumentDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $required,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
        ];
    }
}
