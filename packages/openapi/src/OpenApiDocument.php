<?php

declare(strict_types=1);

namespace Lattice\OpenApi;

use Lattice\OpenApi\Schema\PathItem;

final class OpenApiDocument
{
    /** @var array<string, array<string, array>> */
    private array $paths = [];

    /** @var array<string, array> */
    private array $schemas = [];

    /** @var array<string, array> */
    private array $securitySchemes = [];

    public function __construct(
        public readonly string $title,
        public readonly string $version,
        public readonly ?string $description = null,
    ) {}

    public function addPath(PathItem $pathItem): void
    {
        $this->paths[$pathItem->path][$pathItem->method] = $pathItem->toArray();
    }

    public function addSchema(string $name, array $schema): void
    {
        $this->schemas[$name] = $schema;
    }

    public function addSecurityScheme(string $name, array $scheme): void
    {
        $this->securitySchemes[$name] = $scheme;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $doc = [
            'openapi' => '3.1.0',
            'info' => array_filter([
                'title' => $this->title,
                'version' => $this->version,
                'description' => $this->description,
            ], fn($v) => $v !== null),
        ];

        if ($this->paths !== []) {
            $doc['paths'] = $this->paths;
        }

        $components = [];

        if ($this->schemas !== []) {
            $components['schemas'] = $this->schemas;
        }

        if ($this->securitySchemes !== []) {
            $components['securitySchemes'] = $this->securitySchemes;
        }

        if ($components !== []) {
            $doc['components'] = $components;
        }

        return $doc;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function toYaml(): string
    {
        return $this->arrayToYaml($this->toArray());
    }

    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isSequential($value)) {
                    $yaml .= "{$prefix}{$key}:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "{$prefix}- " . ltrim($this->arrayToYaml($item, $indent + 1));
                        } else {
                            $yaml .= "{$prefix}- {$item}\n";
                        }
                    }
                } else {
                    $yaml .= "{$prefix}{$key}:\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= "{$prefix}{$key}: {$value}\n";
            }
        }

        return $yaml;
    }

    private function isSequential(array $array): bool
    {
        return $array !== [] && array_keys($array) === range(0, count($array) - 1);
    }
}
