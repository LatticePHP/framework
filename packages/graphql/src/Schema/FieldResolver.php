<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Schema;

use Lattice\GraphQL\Attributes\Field;
use ReflectionClass;
use ReflectionProperty;

final class FieldResolver
{
    /**
     * Resolve a field value from an object by checking properties and getter methods.
     */
    public function resolveFieldValue(object $source, string $fieldName): mixed
    {
        $reflection = new ReflectionClass($source);

        // Try direct property access
        if ($reflection->hasProperty($fieldName)) {
            $property = $reflection->getProperty($fieldName);
            if ($property->isPublic()) {
                return $property->getValue($source);
            }
        }

        // Try getter method: getFieldName()
        $getter = 'get' . ucfirst($fieldName);
        if ($reflection->hasMethod($getter) && $reflection->getMethod($getter)->isPublic()) {
            return $reflection->getMethod($getter)->invoke($source);
        }

        // Try is method for booleans: isFieldName()
        $isGetter = 'is' . ucfirst($fieldName);
        if ($reflection->hasMethod($isGetter) && $reflection->getMethod($isGetter)->isPublic()) {
            return $reflection->getMethod($isGetter)->invoke($source);
        }

        // Try the method with same name as field
        if ($reflection->hasMethod($fieldName) && $reflection->getMethod($fieldName)->isPublic()) {
            return $reflection->getMethod($fieldName)->invoke($source);
        }

        return null;
    }

    /**
     * Extract GraphQL field definitions from a class with #[ObjectType].
     *
     * @return array<string, array<string, mixed>>
     */
    public function extractFields(ReflectionClass $reflection, TypeRegistry $typeRegistry): array
    {
        $fields = [];

        // Process properties
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldAttrs = $property->getAttributes(Field::class);
            $fieldAttr = !empty($fieldAttrs) ? $fieldAttrs[0]->newInstance() : null;

            $name = $fieldAttr?->name ?? $property->getName();
            $type = $fieldAttr?->type ?? $typeRegistry->mapPhpType($property->getType());

            if ($fieldAttr?->nullable) {
                $type = str_replace('!', '', $type);
            }

            $fields[$name] = [
                'type' => $type,
                'description' => $fieldAttr?->description,
                'deprecationReason' => $fieldAttr?->deprecationReason,
            ];
        }

        // Process methods with #[Field]
        foreach ($reflection->getMethods() as $method) {
            $fieldAttrs = $method->getAttributes(Field::class);

            if (empty($fieldAttrs)) {
                continue;
            }

            $fieldAttr = $fieldAttrs[0]->newInstance();
            $name = $fieldAttr->name ?? $method->getName();
            $type = $fieldAttr->type ?? $typeRegistry->mapPhpType($method->getReturnType());

            if ($fieldAttr->nullable) {
                $type = str_replace('!', '', $type);
            }

            $fields[$name] = [
                'type' => $type,
                'description' => $fieldAttr->description,
                'deprecationReason' => $fieldAttr->deprecationReason,
            ];
        }

        return $fields;
    }
}
