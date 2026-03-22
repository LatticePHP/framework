<?php

declare(strict_types=1);

namespace Lattice\Validation;

final class ValidationResult
{
    /**
     * @param list<FieldError> $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<FieldError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array{valid: bool, errors: list<array{field: string, message: string, rule: string}>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => array_map(
                static fn(FieldError $error): array => $error->toArray(),
                $this->errors,
            ),
        ];
    }
}
