<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

final class ResourceDefinition
{
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly string $description,
        public readonly string $mimeType,
        public readonly string $className,
        public readonly string $methodName,
    ) {}

    public function isTemplate(): bool
    {
        return str_contains($this->uri, '{');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'uri' => $this->uri,
            'name' => $this->name,
            'description' => $this->description,
            'mimeType' => $this->mimeType,
        ];

        return $data;
    }
}
