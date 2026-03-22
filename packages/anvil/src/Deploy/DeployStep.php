<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy;

interface DeployStep
{
    /**
     * Get the human-readable name of this step.
     */
    public function name(): string;

    /**
     * Execute this deployment step.
     *
     * @throws \RuntimeException If the step fails.
     */
    public function execute(): void;

    /**
     * Roll back this deployment step.
     */
    public function rollback(): void;
}
