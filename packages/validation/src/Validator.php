<?php

declare(strict_types=1);

namespace Lattice\Validation;

use DateTimeImmutable;
use Lattice\Validation\Attributes\ArrayType;
use Lattice\Validation\Attributes\BooleanType;
use Lattice\Validation\Attributes\DateTimeType;
use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\FloatType;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\IntegerType;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;
use Lattice\Validation\Attributes\Url;
use Lattice\Validation\Attributes\Uuid;
use ReflectionClass;
use ReflectionProperty;

final class Validator
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function validate(object $dto): ValidationResult
    {
        $errors = [];
        $reflection = new ReflectionClass($dto);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldName = $property->getName();
            $attributes = $property->getAttributes();

            // Handle uninitialized typed properties (e.g., missing required fields)
            try {
                $value = $property->getValue($dto);
            } catch (\Error) {
                // Property was never set — treat as null for validation purposes
                $value = null;
            }

            // If property is not initialized, check if it's required and report error
            if ($value === null && !$property->isInitialized($dto)) {
                if ($this->hasAttribute($attributes, Required::class)) {
                    $errors[] = new FieldError($fieldName, "The {$fieldName} field is required", 'Required', null);
                }
                continue;
            }

            $isNullable = $this->hasAttribute($attributes, Nullable::class)
                || $property->getType()?->allowsNull();
            $hasNullableAttr = $this->hasAttribute($attributes, Nullable::class);
            $isRequired = $this->hasAttribute($attributes, Required::class);

            // If the value is null and the property has Nullable attribute, skip all other validations
            if ($value === null && $hasNullableAttr) {
                continue;
            }

            // If value is null and not required and type allows null, skip
            if ($value === null && !$isRequired && $isNullable) {
                continue;
            }

            foreach ($attributes as $attribute) {
                $attrInstance = $attribute->newInstance();
                $attrName = $this->getShortClassName($attribute->getName());

                $error = match (true) {
                    $attrInstance instanceof Required => $this->validateRequired($fieldName, $value, $attrName),
                    $attrInstance instanceof StringType => $this->validateStringType($fieldName, $value, $attrInstance, $attrName),
                    $attrInstance instanceof IntegerType => $this->validateIntegerType($fieldName, $value, $attrInstance, $attrName),
                    $attrInstance instanceof FloatType => $this->validateFloatType($fieldName, $value, $attrInstance, $attrName),
                    $attrInstance instanceof BooleanType => $this->validateBooleanType($fieldName, $value, $attrName),
                    $attrInstance instanceof Email => $this->validateEmail($fieldName, $value, $attrName),
                    $attrInstance instanceof Url => $this->validateUrl($fieldName, $value, $attrName),
                    $attrInstance instanceof Uuid => $this->validateUuid($fieldName, $value, $attrName),
                    $attrInstance instanceof InArray => $this->validateInArray($fieldName, $value, $attrInstance, $attrName),
                    $attrInstance instanceof ArrayType => $this->validateArrayType($fieldName, $value, $attrInstance, $attrName),
                    $attrInstance instanceof DateTimeType => $this->validateDateTimeType($fieldName, $value, $attrInstance, $attrName),
                    default => null,
                };

                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }

        return new ValidationResult($errors);
    }

    private function validateRequired(string $field, mixed $value, string $rule): ?FieldError
    {
        if ($value === null || $value === '' || $value === []) {
            return new FieldError($field, "The {$field} field is required", $rule, $value);
        }

        return null;
    }

    private function validateStringType(string $field, mixed $value, StringType $attr, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return new FieldError($field, "The {$field} field must be a string", $rule, $value);
        }

        $length = mb_strlen($value);

        if ($attr->minLength !== null && $length < $attr->minLength) {
            return new FieldError(
                $field,
                "The {$field} field must be at least {$attr->minLength} characters",
                $rule,
                $value,
            );
        }

        if ($attr->maxLength !== null && $length > $attr->maxLength) {
            return new FieldError(
                $field,
                "The {$field} field must be at most {$attr->maxLength} characters",
                $rule,
                $value,
            );
        }

        if ($attr->pattern !== null && !preg_match($attr->pattern, $value)) {
            return new FieldError(
                $field,
                "The {$field} field does not match the required pattern",
                $rule,
                $value,
            );
        }

        return null;
    }

    private function validateIntegerType(string $field, mixed $value, IntegerType $attr, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            return new FieldError($field, "The {$field} field must be an integer", $rule, $value);
        }

        if ($attr->min !== null && $value < $attr->min) {
            return new FieldError(
                $field,
                "The {$field} field must be at least {$attr->min}",
                $rule,
                $value,
            );
        }

        if ($attr->max !== null && $value > $attr->max) {
            return new FieldError(
                $field,
                "The {$field} field must be at most {$attr->max}",
                $rule,
                $value,
            );
        }

        return null;
    }

    private function validateFloatType(string $field, mixed $value, FloatType $attr, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_float($value) && !is_int($value)) {
            return new FieldError($field, "The {$field} field must be a number", $rule, $value);
        }

        $floatVal = (float) $value;

        if ($attr->min !== null && $floatVal < $attr->min) {
            return new FieldError(
                $field,
                "The {$field} field must be at least {$attr->min}",
                $rule,
                $value,
            );
        }

        if ($attr->max !== null && $floatVal > $attr->max) {
            return new FieldError(
                $field,
                "The {$field} field must be at most {$attr->max}",
                $rule,
                $value,
            );
        }

        return null;
    }

    private function validateBooleanType(string $field, mixed $value, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_bool($value)) {
            return new FieldError($field, "The {$field} field must be a boolean", $rule, $value);
        }

        return null;
    }

    private function validateEmail(string $field, mixed $value, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return new FieldError($field, "The {$field} field must be a valid email address", $rule, $value);
        }

        return null;
    }

    private function validateUrl(string $field, mixed $value, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return new FieldError($field, "The {$field} field must be a valid URL", $rule, $value);
        }

        return null;
    }

    private function validateUuid(string $field, mixed $value, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || !preg_match(self::UUID_PATTERN, $value)) {
            return new FieldError($field, "The {$field} field must be a valid UUID", $rule, $value);
        }

        return null;
    }

    private function validateInArray(string $field, mixed $value, InArray $attr, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!in_array($value, $attr->values, true)) {
            $allowed = implode(', ', $attr->values);
            return new FieldError(
                $field,
                "The {$field} field must be one of: {$allowed}",
                $rule,
                $value,
            );
        }

        return null;
    }

    private function validateArrayType(string $field, mixed $value, ArrayType $attr, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            return new FieldError($field, "The {$field} field must be an array", $rule, $value);
        }

        $count = count($value);

        if ($attr->minItems !== null && $count < $attr->minItems) {
            return new FieldError(
                $field,
                "The {$field} field must have at least {$attr->minItems} items",
                $rule,
                $value,
            );
        }

        if ($attr->maxItems !== null && $count > $attr->maxItems) {
            return new FieldError(
                $field,
                "The {$field} field must have at most {$attr->maxItems} items",
                $rule,
                $value,
            );
        }

        return null;
    }

    private function validateDateTimeType(string $field, mixed $value, DateTimeType $attr, string $rule): ?FieldError
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return new FieldError($field, "The {$field} field must be a valid date string", $rule, $value);
        }

        $parsed = DateTimeImmutable::createFromFormat($attr->format, $value);

        if ($parsed === false || $parsed->format($attr->format) !== $value) {
            return new FieldError(
                $field,
                "The {$field} field must match the date format {$attr->format}",
                $rule,
                $value,
            );
        }

        return null;
    }

    /**
     * @param array<\ReflectionAttribute> $attributes
     * @param class-string $className
     */
    private function hasAttribute(array $attributes, string $className): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $className) {
                return true;
            }
        }

        return false;
    }

    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
