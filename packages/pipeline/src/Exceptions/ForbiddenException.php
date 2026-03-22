<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Exceptions;

use Lattice\Http\Exception\ForbiddenException as HttpForbiddenException;

class ForbiddenException extends HttpForbiddenException
{
    public function __construct(string $message = 'Forbidden', ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
