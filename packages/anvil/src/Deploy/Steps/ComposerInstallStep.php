<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy\Steps;

use Lattice\Anvil\Deploy\DeployStep;
use Symfony\Component\Process\Process;

final class ComposerInstallStep implements DeployStep
{
    public function __construct(
        private readonly string $projectPath,
    ) {
    }

    public function name(): string
    {
        return 'Composer install (production)';
    }

    public function execute(): void
    {
        $process = new Process(
            ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            $this->projectPath,
        );
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Composer install failed: ' . $process->getErrorOutput(),
            );
        }
    }

    public function rollback(): void
    {
        // Re-install with dev dependencies restored
        $process = new Process(
            ['composer', 'install', '--no-interaction'],
            $this->projectPath,
        );
        $process->setTimeout(300);
        $process->run();
    }
}
