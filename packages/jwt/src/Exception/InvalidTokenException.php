<?php

declare(strict_types=1);

namespace Lattice\Jwt\Exception;

final class InvalidTokenException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid token', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
