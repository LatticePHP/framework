<?php

declare(strict_types=1);

namespace Lattice\Validation;

use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Validation\Exceptions\ValidationException;
use ReflectionClass;
use ReflectionProperty;

final class ValidationPipe implements PipeInterface
{
    private readonly Validator $validator;

    public function __construct(?Validator $validator = null)
    {
        $this->validator = $validator ?? new Validator();
    }

    public function transform(mixed $value, array $metadata = []): mixed
    {
        if (!is_object($value) || !$this->hasValidationAttributes($value)) {
            return $value;
        }

        $result = $this->validator->validate($value);

        if (!$result->isValid()) {
            throw new ValidationException($result);
        }

        return $value;
    }

    private function hasValidationAttributes(object $dto): bool
    {
        $reflection = new ReflectionClass($dto);
        $attributeNamespace = 'Lattice\\Validation\\Attributes\\';

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if (str_starts_with($attribute->getName(), $attributeNamespace)) {
                    return true;
                }
            }
        }

        return false;
    }
}
