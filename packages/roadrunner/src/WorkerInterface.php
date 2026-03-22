<?php

declare(strict_types=1);

namespace Lattice\RoadRunner;

interface WorkerInterface
{
    public function start(): void;

    public function stop(): void;

    public function isRunning(): bool;
}
