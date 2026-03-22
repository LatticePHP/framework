<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy\Steps;

use Lattice\Anvil\Deploy\DeployStep;
use Symfony\Component\Process\Process;

final class QueueRestartStep implements DeployStep
{
    public function __construct(
        private readonly string $projectPath,
    ) {
    }

    public function name(): string
    {
        return 'Restart queue workers';
    }

    public function execute(): void
    {
        // Signal queue workers to restart after their current job
        $process = new Process(
            ['php', 'lattice', 'queue:restart'],
            $this->projectPath,
        );
        $process->setTimeout(30);
        $process->run();

        // Queue restart is best-effort; not all deployments have queue workers
        // We only fail if the command itself errors out (not if there are no workers)
        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();

            // If the command simply does not exist or no workers are running, that is acceptable
            if (str_contains($errorOutput, 'not defined') || str_contains($errorOutput, 'not found')) {
                return;
            }

            throw new \RuntimeException(
                'Queue restart failed: ' . $errorOutput,
            );
        }
    }

    public function rollback(): void
    {
        // Re-restart workers after code rollback
        $process = new Process(
            ['php', 'lattice', 'queue:restart'],
            $this->projectPath,
        );
        $process->setTimeout(30);
        $process->run();
    }
}
