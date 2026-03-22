<?php

declare(strict_types=1);

namespace Lattice\Jwt\Exception;

final class ExpiredTokenException extends \RuntimeException
{
    public function __construct(string $message = 'Token has expired', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
