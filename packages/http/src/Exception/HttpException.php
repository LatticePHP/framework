<?php

declare(strict_types=1);

namespace Lattice\Http\Exception;

class HttpException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
