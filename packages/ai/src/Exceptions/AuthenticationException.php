<?php

declare(strict_types=1);

namespace Lattice\Ai\Exceptions;

final class AuthenticationException extends AiException
{
    public function __construct(
        string $message = 'Authentication failed. Check your API key.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $previous);
    }
}
