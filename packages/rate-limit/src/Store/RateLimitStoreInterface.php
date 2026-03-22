<?php

declare(strict_types=1);

namespace Lattice\RateLimit\Store;

interface RateLimitStoreInterface
{
    /**
     * Increment the counter for the given key.
     * Returns the new count after incrementing.
     */
    public function increment(string $key, int $decaySeconds): int;

    /**
     * Get the current count for the given key.
     */
    public function get(string $key): int;

    /**
     * Reset the counter for the given key.
     */
    public function reset(string $key): void;
}
