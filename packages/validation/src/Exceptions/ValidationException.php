<?php

declare(strict_types=1);

namespace Lattice\Validation\Exceptions;

use Lattice\Validation\ValidationResult;
use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(
        private readonly ValidationResult $validationResult,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }

    public function getValidationResult(): ValidationResult
    {
        return $this->validationResult;
    }
}
