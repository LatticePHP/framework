<?php

declare(strict_types=1);

namespace Lattice\Ai\Exceptions;

final class ContextLengthExceededException extends AiException
{
    public function __construct(
        string $message = 'Context length exceeded for this model.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 400, $previous);
    }
}
