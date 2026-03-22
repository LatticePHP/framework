<?php

declare(strict_types=1);

namespace Lattice\RateLimit\Store;

final class InMemoryRateLimitStore implements RateLimitStoreInterface
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, int> Unix timestamp when the key expires */
    private array $expiresAt = [];

    public function increment(string $key, int $decaySeconds): int
    {
        $this->evictIfExpired($key);

        if (!isset($this->counters[$key])) {
            $this->counters[$key] = 0;
            $this->expiresAt[$key] = time() + $decaySeconds;
        }

        return ++$this->counters[$key];
    }

    public function get(string $key): int
    {
        $this->evictIfExpired($key);

        return $this->counters[$key] ?? 0;
    }

    public function reset(string $key): void
    {
        unset($this->counters[$key], $this->expiresAt[$key]);
    }

    private function evictIfExpired(string $key): void
    {
        if (isset($this->expiresAt[$key]) && time() >= $this->expiresAt[$key]) {
            $this->reset($key);
        }
    }
}
