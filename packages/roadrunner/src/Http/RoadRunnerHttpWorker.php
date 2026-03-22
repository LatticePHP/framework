<?php

declare(strict_types=1);

namespace Lattice\RoadRunner\Http;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\RoadRunner\ContainerResetter;
use Lattice\RoadRunner\GracefulShutdown;
use Lattice\RoadRunner\MemoryGuard;
use Lattice\RoadRunner\RoadRunnerConfig;
use Lattice\RoadRunner\WorkerInterface;
use Lattice\RoadRunner\WorkerLifecycle;

/**
 * Adapts the LatticePHP HTTP kernel to run under RoadRunner.
 *
 * This class provides the integration layer that:
 * - Receives PSR-7 requests from RoadRunner
 * - Converts them to Lattice Request objects
 * - Passes them through the HttpKernel
 * - Returns PSR-7 responses to RoadRunner
 * - Resets container state between requests
 *
 * Note: Actual RoadRunner dependencies (spiral/roadrunner-http) are not included.
 * This class defines the integration contract. To use it, install the actual
 * RoadRunner packages and provide the PSR-7 HTTP worker.
 */
final class RoadRunnerHttpWorker implements WorkerInterface
{
    private bool $running = false;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ContainerResetter $containerResetter,
        private readonly WorkerLifecycle $lifecycle,
        private readonly MemoryGuard $memoryGuard,
        private readonly GracefulShutdown $gracefulShutdown,
        private readonly RoadRunnerConfig $config,
    ) {}

    public function start(): void
    {
        $this->running = true;
        $this->lifecycle->triggerStartup();

        // In a real integration, this would enter the RoadRunner worker loop:
        //
        // while ($req = $psr7Worker->waitRequest()) {
        //     $latticeRequest = $this->convertRequest($req);
        //     $response = $kernel->handle($latticeRequest);
        //     $psr7Worker->respond($this->convertResponse($response));
        //     $this->afterRequest();
        // }
    }

    public function stop(): void
    {
        $this->lifecycle->triggerDrain();
        $this->lifecycle->triggerShutdown();
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Called after each request to reset state and check memory limits.
     * In a real integration, this is called inside the worker loop.
     */
    public function afterRequest(): void
    {
        $this->lifecycle->triggerRequest();
        $this->containerResetter->reset($this->container);

        if ($this->memoryGuard->check($this->config->maxMemory)) {
            $this->stop();
        }

        if ($this->gracefulShutdown->shouldStop()) {
            $this->stop();
        }
    }
}
