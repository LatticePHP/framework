<?php

declare(strict_types=1);

namespace Lattice\Http\Exception;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
