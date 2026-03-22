<?php

declare(strict_types=1);

namespace Lattice\OpenSwoole;

final class OpenSwooleConfig
{
    public function __construct(
        public readonly string $host = '0.0.0.0',
        public readonly int $port = 9501,
        public readonly int $workerNum = 4,
        public readonly bool $enableCoroutine = true,
    ) {}
}
