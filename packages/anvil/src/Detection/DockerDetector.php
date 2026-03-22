<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

use Symfony\Component\Process\Process;

final class DockerDetector implements DetectorInterface
{
    public function detect(): DetectionResult
    {
        $process = new Process(['docker', '--version']);
        $process->run();

        $output = $process->getOutput();

        if (!$process->isSuccessful()) {
            return new DetectionResult(
                name: 'Docker',
                installed: false,
            );
        }

        $version = $this->parseVersion($output);
        $status = $this->detectStatus();

        return new DetectionResult(
            name: 'Docker',
            installed: true,
            version: $version,
            status: $status,
            details: [
                'raw_output' => trim($output),
            ],
        );
    }

    private function parseVersion(string $output): ?string
    {
        if (preg_match('/Docker version (\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function detectStatus(): string
    {
        $process = new Process(['docker', 'info']);
        $process->run();

        return $process->isSuccessful() ? 'running' : 'stopped';
    }
}
