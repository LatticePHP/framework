<?php

declare(strict_types=1);

namespace Lattice\Microservices;

final readonly class HandlerMatch
{
    public function __construct(
        public string $handlerClass,
        public string $method,
        public string $pattern,
    ) {}
}
