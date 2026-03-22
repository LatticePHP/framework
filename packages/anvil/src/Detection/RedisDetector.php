<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

use Symfony\Component\Process\Process;

final class RedisDetector implements DetectorInterface
{
    public function detect(): DetectionResult
    {
        $process = new Process(['redis-server', '--version']);
        $process->run();

        $output = $process->getOutput();

        if (!$process->isSuccessful()) {
            return new DetectionResult(
                name: 'Redis',
                installed: false,
            );
        }

        $version = $this->parseVersion($output);
        $status = $this->detectStatus();

        return new DetectionResult(
            name: 'Redis',
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
        if (preg_match('/v=(\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function detectStatus(): string
    {
        $process = new Process(['pgrep', '-x', 'redis-server']);
        $process->run();

        return $process->isSuccessful() ? 'running' : 'stopped';
    }
}
