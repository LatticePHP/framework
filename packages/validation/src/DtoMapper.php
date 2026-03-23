<?php

declare(strict_types=1);

namespace Lattice\Validation;

use Lattice\Validation\Exceptions\MappingException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

final class DtoMapper
{
    /**
     * Maps raw array data to a typed DTO object.
     *
     * @template T of object
     * @param array<string, mixed> $data
     * @param class-string<T> $dtoClass
     * @return T
     * @throws MappingException
     */
    public function map(array $data, string $dtoClass): object
    {
        try {
            $reflection = new ReflectionClass($dtoClass);
        } catch (ReflectionException $e) {
            throw new MappingException("Class {$dtoClass} does not exist", 0, $e);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // No constructor — set public properties directly
            $instance = $reflection->newInstanceWithoutConstructor();
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $name = $prop->getName();
                if (array_key_exists($name, $data)) {
                    $prop->setValue($instance, $data[$name]);
                }
            }
            return $instance;
        }

        $args = [];
        $missingFields = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $args[] = $this->coerceValue($data[$name], $param);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $missingFields[] = $name;
                // Use null as placeholder to continue collecting missing fields
                $args[] = null;
            }
        }

        if (!empty($missingFields)) {
            throw new MappingException(
                "Missing required fields: " . implode(', ', $missingFields) . " for {$dtoClass}",
            );
        }

        try {
            /** @var T */
            return $reflection->newInstanceArgs($args);
        } catch (\Throwable $e) {
            throw new MappingException(
                "Failed to instantiate {$dtoClass}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    private function coerceValue(mixed $value, ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => $this->coerceToInt($value),
            'float' => $this->coerceToFloat($value),
            'bool' => $this->coerceToBool($value),
            'string' => is_scalar($value) ? (string) $value : $value,
            'array' => is_array($value) ? $value : $value,
            default => $value,
        };
    }

    private function coerceToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return (int) $value;
    }

    private function coerceToFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        return (float) $value;
    }

    private function coerceToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off', '' => false,
                default => (bool) $value,
            };
        }

        return (bool) $value;
    }
}
