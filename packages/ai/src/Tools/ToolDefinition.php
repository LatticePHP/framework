<?php

declare(strict_types=1);

namespace Lattice\Ai\Tools;

final readonly class ToolDefinition
{
    /**
     * @param array<string, mixed> $parameters JSON Schema for the tool's parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {}

    /**
     * Convert to the standard format used in API requests.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['name'] ?? ''),
            (string) ($data['description'] ?? ''),
            (array) ($data['parameters'] ?? []),
        );
    }
}
