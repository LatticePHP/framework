<?php

declare(strict_types=1);

namespace Lattice\Mcp\Transport;

interface TransportInterface
{
    /**
     * Start the transport, processing messages.
     */
    public function start(): void;

    /**
     * Stop the transport gracefully.
     */
    public function stop(): void;

    /**
     * Check if the transport is currently running.
     */
    public function isRunning(): bool;
}
