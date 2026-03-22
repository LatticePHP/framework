<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

use Symfony\Component\Process\Process;

final class NodeDetector implements DetectorInterface
{
    public function detect(): DetectionResult
    {
        $process = new Process(['node', '-v']);
        $process->run();

        $output = $process->getOutput();

        if (!$process->isSuccessful()) {
            return new DetectionResult(
                name: 'Node.js',
                installed: false,
            );
        }

        $version = $this->parseVersion($output);
        $nvmInstalled = $this->detectNvm();

        return new DetectionResult(
            name: 'Node.js',
            installed: true,
            version: $version,
            status: 'installed',
            details: [
                'nvm_installed' => $nvmInstalled,
                'raw_output' => trim($output),
            ],
        );
    }

    private function parseVersion(string $output): ?string
    {
        if (preg_match('/v?(\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function detectNvm(): bool
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        $nvmDir = $home . '/.nvm';

        return is_dir($nvmDir);
    }
}
