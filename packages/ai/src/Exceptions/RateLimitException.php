<?php

declare(strict_types=1);

namespace Lattice\Ai\Exceptions;

final class RateLimitException extends AiException
{
    public function __construct(
        string $message = 'Rate limit exceeded.',
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $previous);
    }
}
