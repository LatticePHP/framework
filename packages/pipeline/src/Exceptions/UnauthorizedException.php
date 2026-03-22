<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Exceptions;

use Lattice\Http\Exception\UnauthorizedException as HttpUnauthorizedException;

class UnauthorizedException extends HttpUnauthorizedException
{
    public function __construct(string $message = 'Unauthorized', ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
