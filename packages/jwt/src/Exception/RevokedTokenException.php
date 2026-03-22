<?php

declare(strict_types=1);

namespace Lattice\Jwt\Exception;

final class RevokedTokenException extends \RuntimeException
{
    public function __construct(string $message = 'Token has been revoked', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
