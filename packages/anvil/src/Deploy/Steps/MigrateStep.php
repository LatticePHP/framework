<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy\Steps;

use Lattice\Anvil\Deploy\DeployStep;
use Symfony\Component\Process\Process;

final class MigrateStep implements DeployStep
{
    public function __construct(
        private readonly string $projectPath,
    ) {
    }

    public function name(): string
    {
        return 'Run database migrations';
    }

    public function execute(): void
    {
        $process = new Process(
            ['php', 'lattice', 'migrate', '--force'],
            $this->projectPath,
        );
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Migration failed: ' . $process->getErrorOutput(),
            );
        }
    }

    public function rollback(): void
    {
        $process = new Process(
            ['php', 'lattice', 'migrate:rollback'],
            $this->projectPath,
        );
        $process->setTimeout(120);
        $process->run();
    }
}
