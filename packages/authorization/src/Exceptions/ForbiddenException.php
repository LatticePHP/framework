<?php

declare(strict_types=1);

namespace Lattice\Authorization\Exceptions;

use RuntimeException;

final class ForbiddenException extends RuntimeException
{
    public function __construct(string $message = 'Forbidden.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
