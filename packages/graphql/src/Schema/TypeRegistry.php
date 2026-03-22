<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Schema;

use ReflectionNamedType;
use ReflectionType;

final class TypeRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $objectTypes = [];

    /** @var array<string, array<string, mixed>> */
    private array $inputTypes = [];

    /** @var array<string, array<string, string>> */
    private array $enumTypes = [];

    /**
     * Map a PHP reflection type to a GraphQL type string.
     */
    public function mapPhpType(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'String';
        }

        if (!$type instanceof ReflectionNamedType) {
            return 'String';
        }

        $name = $type->getName();
        $graphqlType = $this->mapScalarType($name);
        $isNullable = $type->allowsNull();

        if ($isNullable) {
            return $graphqlType;
        }

        return $graphqlType . '!';
    }

    /**
     * Map a PHP scalar type name to a GraphQL type name.
     */
    public function mapScalarType(string $phpType): string
    {
        return match ($phpType) {
            'string' => 'String',
            'int' => 'Int',
            'float' => 'Float',
            'bool' => 'Boolean',
            'array' => '[String]',
            default => $this->resolveCustomType($phpType),
        };
    }

    /**
     * Parse a GraphQL type string into its components.
     *
     * @return array{type: string, nonNull: bool, list: bool, listItemNonNull: bool}
     */
    public function parseTypeString(string $typeString): array
    {
        $nonNull = false;
        $list = false;
        $listItemNonNull = false;

        $type = $typeString;

        // Check for non-null wrapper
        if (str_ends_with($type, '!')) {
            $nonNull = true;
            $type = substr($type, 0, -1);
        }

        // Check for list wrapper
        if (str_starts_with($type, '[') && str_ends_with($type, ']')) {
            $list = true;
            $type = substr($type, 1, -1);

            if (str_ends_with($type, '!')) {
                $listItemNonNull = true;
                $type = substr($type, 0, -1);
            }
        }

        return [
            'type' => $type,
            'nonNull' => $nonNull,
            'list' => $list,
            'listItemNonNull' => $listItemNonNull,
        ];
    }

    /**
     * Register a GraphQL object type.
     *
     * @param array<string, array<string, mixed>> $fields
     */
    public function registerObjectType(string $name, array $fields, ?string $description = null): void
    {
        $this->objectTypes[$name] = [
            'fields' => $fields,
            'description' => $description,
        ];
    }

    /**
     * Register a GraphQL input type.
     *
     * @param array<string, array<string, mixed>> $fields
     */
    public function registerInputType(string $name, array $fields, ?string $description = null): void
    {
        $this->inputTypes[$name] = [
            'fields' => $fields,
            'description' => $description,
        ];
    }

    /**
     * Register a GraphQL enum type.
     *
     * @param array<string, string> $values Map of enum value name to description
     */
    public function registerEnumType(string $name, array $values, ?string $description = null): void
    {
        $this->enumTypes[$name] = [
            'values' => $values,
            'description' => $description,
        ];
    }

    public function hasObjectType(string $name): bool
    {
        return isset($this->objectTypes[$name]);
    }

    public function hasInputType(string $name): bool
    {
        return isset($this->inputTypes[$name]);
    }

    public function hasEnumType(string $name): bool
    {
        return isset($this->enumTypes[$name]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getObjectType(string $name): ?array
    {
        return $this->objectTypes[$name] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInputType(string $name): ?array
    {
        return $this->inputTypes[$name] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEnumType(string $name): ?array
    {
        return $this->enumTypes[$name] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getObjectTypes(): array
    {
        return $this->objectTypes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getInputTypes(): array
    {
        return $this->inputTypes;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getEnumTypes(): array
    {
        return $this->enumTypes;
    }

    /**
     * Resolve a custom (non-scalar) PHP type to a GraphQL type name.
     */
    private function resolveCustomType(string $phpType): string
    {
        // Extract short class name
        $parts = explode('\\', $phpType);
        $shortName = end($parts);

        // Check if it's a registered enum or object type
        if ($this->hasEnumType($shortName)) {
            return $shortName;
        }

        if ($this->hasObjectType($shortName)) {
            return $shortName;
        }

        if ($this->hasInputType($shortName)) {
            return $shortName;
        }

        return $shortName;
    }
}
