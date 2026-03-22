<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy\Steps;

use Lattice\Anvil\Deploy\DeployStep;
use Symfony\Component\Process\Process;

final class GitPullStep implements DeployStep
{
    private ?string $previousHead = null;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $branch = 'main',
    ) {
    }

    public function name(): string
    {
        return "Git pull ({$this->branch})";
    }

    public function execute(): void
    {
        $this->previousHead = $this->getCurrentHead();

        $process = new Process(
            ['git', 'pull', 'origin', $this->branch],
            $this->projectPath,
        );
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Git pull failed: ' . $process->getErrorOutput(),
            );
        }
    }

    public function rollback(): void
    {
        if ($this->previousHead === null) {
            return;
        }

        $process = new Process(
            ['git', 'reset', '--hard', $this->previousHead],
            $this->projectPath,
        );
        $process->setTimeout(30);
        $process->run();
    }

    private function getCurrentHead(): ?string
    {
        $process = new Process(
            ['git', 'rev-parse', 'HEAD'],
            $this->projectPath,
        );
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }
}
