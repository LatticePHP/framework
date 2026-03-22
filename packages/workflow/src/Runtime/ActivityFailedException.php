<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

final class ActivityFailedException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $originalExceptionClass,
    ) {
        parent::__construct($message);
    }

    public function getOriginalExceptionClass(): string
    {
        return $this->originalExceptionClass;
    }
}
