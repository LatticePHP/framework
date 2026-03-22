<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy\Steps;

use Lattice\Anvil\Deploy\DeployStep;
use Symfony\Component\Process\Process;

final class CacheClearStep implements DeployStep
{
    public function __construct(
        private readonly string $projectPath,
    ) {
    }

    public function name(): string
    {
        return 'Clear caches (config, route, view)';
    }

    public function execute(): void
    {
        $commands = [
            ['php', 'lattice', 'config:clear'],
            ['php', 'lattice', 'route:clear'],
        ];

        foreach ($commands as $command) {
            $process = new Process($command, $this->projectPath);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(
                    'Cache clear failed (' . implode(' ', $command) . '): ' . $process->getErrorOutput(),
                );
            }
        }
    }

    public function rollback(): void
    {
        // Rebuild caches after rollback
        $commands = [
            ['php', 'lattice', 'config:cache'],
            ['php', 'lattice', 'route:cache'],
        ];

        foreach ($commands as $command) {
            $process = new Process($command, $this->projectPath);
            $process->setTimeout(30);
            $process->run();
        }
    }
}
