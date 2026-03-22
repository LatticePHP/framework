<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Schema;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionEnum;

final class SchemaGenerator
{
    /**
     * Generate a JSON Schema from a PHP class.
     *
     * @return array<string, mixed>
     */
    public function fromClass(string $className): array
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class '{$className}' does not exist.");
        }

        $reflection = new ReflectionClass($className);
        $properties = $this->resolveProperties($reflection);

        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        $required = [];

        foreach ($properties as $property) {
            $name = $property->getName();
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType) {
                $schema['properties'][$name] = new \stdClass();
                continue;
            }

            $propSchema = $this->resolveType($type);
            $schema['properties'][$name] = $propSchema;

            if (!$type->allowsNull()) {
                $required[] = $name;
            }
        }

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @return ReflectionProperty[]
     */
    private function resolveProperties(ReflectionClass $reflection): array
    {
        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            $promoted = [];
            foreach ($constructor->getParameters() as $param) {
                if ($param->isPromoted()) {
                    $promoted[$param->getName()] = $reflection->getProperty($param->getName());
                }
            }

            if ($promoted !== []) {
                return array_values($promoted);
            }
        }

        return $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveType(ReflectionNamedType $type): array
    {
        $typeName = $type->getName();
        $isNullable = $type->allowsNull();

        // Check if it's an enum
        if (!$type->isBuiltin() && enum_exists($typeName)) {
            $enumReflection = new ReflectionEnum($typeName);
            $backingType = $enumReflection->getBackingType();
            $cases = array_map(
                fn($case) => $case->getBackingValue(),
                $enumReflection->getCases(),
            );

            $schema = [
                'type' => $backingType instanceof ReflectionNamedType
                    ? $this->mapScalarType($backingType->getName())
                    : 'string',
                'enum' => $cases,
            ];

            return $schema;
        }

        $mappedType = $this->mapScalarType($typeName);

        if ($isNullable) {
            return ['type' => [$mappedType, 'null']];
        }

        return ['type' => $mappedType];
    }

    private function mapScalarType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'string' => 'string',
            'array' => 'array',
            default => 'object',
        };
    }
}
