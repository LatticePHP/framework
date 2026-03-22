<?php

declare(strict_types=1);

namespace Lattice\Http\Exception;

class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad Request', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
