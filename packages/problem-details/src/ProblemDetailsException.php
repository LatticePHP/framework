<?php

declare(strict_types=1);

namespace Lattice\ProblemDetails;

final class ProblemDetailsException extends \RuntimeException
{
    public function __construct(
        public readonly ProblemDetails $problemDetails,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $problemDetails->detail ?? $problemDetails->title,
            $problemDetails->status,
            $previous,
        );
    }
}
