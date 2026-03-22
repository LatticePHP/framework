<?php

declare(strict_types=1);

namespace Lattice\RateLimit;

use Lattice\RateLimit\Store\RateLimitStoreInterface;

final class RateLimiter
{
    public function __construct(
        private readonly RateLimitStoreInterface $store,
    ) {}

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult
    {
        $currentCount = $this->store->get($key);

        if ($currentCount >= $maxAttempts) {
            return new RateLimitResult(
                allowed: false,
                remaining: 0,
                retryAfter: $decaySeconds,
                limit: $maxAttempts,
            );
        }

        $newCount = $this->store->increment($key, $decaySeconds);

        return new RateLimitResult(
            allowed: true,
            remaining: max(0, $maxAttempts - $newCount),
            retryAfter: null,
            limit: $maxAttempts,
        );
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $currentCount = $this->store->get($key);

        return max(0, $maxAttempts - $currentCount);
    }

    public function clear(string $key): void
    {
        $this->store->reset($key);
    }
}
