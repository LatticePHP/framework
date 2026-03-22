<?php

declare(strict_types=1);

namespace Lattice\RoadRunner;

final class GracefulShutdown
{
    private bool $stopped = false;

    /**
     * Register signal handlers for SIGTERM and SIGINT.
     * On platforms without pcntl (e.g., Windows), this is a no-op.
     */
    public function register(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, fn () => $this->trigger());
        pcntl_signal(SIGINT, fn () => $this->trigger());
    }

    /**
     * Check whether a stop has been requested.
     */
    public function shouldStop(): bool
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        return $this->stopped;
    }

    /**
     * Manually trigger a stop.
     */
    public function trigger(): void
    {
        $this->stopped = true;
    }

    /**
     * Reset the stop flag.
     */
    public function reset(): void
    {
        $this->stopped = false;
    }
}
