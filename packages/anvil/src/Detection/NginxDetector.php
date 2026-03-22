<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

use Symfony\Component\Process\Process;

final class NginxDetector implements DetectorInterface
{
    public function detect(): DetectionResult
    {
        $process = new Process(['nginx', '-v']);
        $process->run();

        $output = $process->getErrorOutput() ?: $process->getOutput();

        if (!$process->isSuccessful()) {
            return new DetectionResult(
                name: 'Nginx',
                installed: false,
            );
        }

        $version = $this->parseVersion($output);
        $status = $this->detectStatus();

        return new DetectionResult(
            name: 'Nginx',
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
        if (preg_match('/nginx\/(\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function detectStatus(): string
    {
        $process = new Process(['pgrep', '-x', 'nginx']);
        $process->run();

        return $process->isSuccessful() ? 'running' : 'stopped';
    }
}
