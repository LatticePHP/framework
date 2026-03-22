<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Exceptions;

class ValidationException extends PipelineException
{
    /** @param array<string, array<string>> $errors */
    public function __construct(
        private readonly array $errors = [],
        string $message = 'Validation failed',
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, array<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
