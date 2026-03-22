<?php

declare(strict_types=1);

namespace Lattice\Cache;

final class CacheManager
{
    /** @var array<string, CacheInterface> */
    private array $drivers = [];

    public function store(string $name = 'default'): CacheInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new \InvalidArgumentException("Cache store [{$name}] is not configured.");
        }

        return $this->drivers[$name];
    }

    public function addDriver(string $name, CacheInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }
}
