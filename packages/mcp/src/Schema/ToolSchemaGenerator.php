<?php

declare(strict_types=1);

namespace Lattice\Mcp\Schema;

final class ToolSchemaGenerator
{
    public function __construct(
        private readonly ParameterExtractor $extractor = new ParameterExtractor(),
    ) {}

    /**
     * Generate a JSON Schema for a method's input parameters.
     *
     * @return array<string, mixed>
     */
    public function generate(\ReflectionMethod $method): array
    {
        $params = $this->extractor->extract($method);

        $properties = [];
        $required = [];

        foreach ($params as $param) {
            $property = $this->mapTypeToSchema($param);

            if ($param->description !== '') {
                $property['description'] = $param->description;
            }

            if ($param->hasDefault && $param->defaultValue !== null) {
                $property['default'] = $param->defaultValue;
            }

            $properties[$param->name] = $property;

            if (!$param->hasDefault && !$param->nullable) {
                $required[] = $param->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties === [] ? new \stdClass() : $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTypeToSchema(ParameterInfo $param): array
    {
        if ($param->type === null) {
            return ['type' => 'string'];
        }

        if ($param->type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($param->type->getTypes() as $t) {
                if ($t instanceof \ReflectionNamedType) {
                    $mapped = $this->mapNamedType($t);
                    if ($mapped !== null) {
                        $types[] = $mapped;
                    }
                }
            }

            return $types === [] ? ['type' => 'string'] : ['oneOf' => $types];
        }

        if ($param->type instanceof \ReflectionNamedType) {
            $schema = $this->mapNamedType($param->type);

            return $schema ?? ['type' => 'string'];
        }

        return ['type' => 'string'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapNamedType(\ReflectionNamedType $type): ?array
    {
        if ($type->allowsNull() && $type->getName() === 'null') {
            return null;
        }

        $typeName = $type->getName();

        $schema = match ($typeName) {
            'string' => ['type' => 'string'],
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => $this->mapClassType($typeName),
        };

        if ($type->allowsNull() && $typeName !== 'null') {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapClassType(string $className): array
    {
        if (!class_exists($className) && !enum_exists($className)) {
            return ['type' => 'string'];
        }

        $reflection = new \ReflectionClass($className);

        // Backed enum -> string with enum values
        if ($reflection->isEnum()) {
            /** @var \ReflectionEnum $enumReflection */
            $enumReflection = new \ReflectionEnum($className);

            if ($enumReflection->isBacked()) {
                $backingType = $enumReflection->getBackingType();
                $jsonType = ($backingType instanceof \ReflectionNamedType && $backingType->getName() === 'int')
                    ? 'integer'
                    : 'string';

                $values = array_map(
                    static fn(\UnitEnum $case): string|int => $case->value,
                    $className::cases(),
                );

                return ['type' => $jsonType, 'enum' => $values];
            }
        }

        return ['type' => 'object'];
    }
}
