<?php

declare(strict_types=1);

namespace Lattice\Cache;

final readonly class RedisConfig
{
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 6379,
        public ?string $password = null,
        public int $database = 0,
        public string $prefix = '',
    ) {}
}
