<?php

declare(strict_types=1);

namespace Lattice\RoadRunner;

final class MemoryGuard
{
    /**
     * Check if memory usage exceeds the given limit.
     *
     * @param int $limitMb Memory limit in megabytes
     * @return bool True if the limit has been exceeded
     */
    public function check(int $limitMb): bool
    {
        return $this->getCurrentUsageMb() >= (float) $limitMb;
    }

    /**
     * Get current memory usage in megabytes.
     */
    public function getCurrentUsageMb(): float
    {
        return memory_get_usage(true) / 1024.0 / 1024.0;
    }
}
