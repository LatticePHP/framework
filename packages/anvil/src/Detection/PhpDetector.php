<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

use Symfony\Component\Process\Process;

final class PhpDetector implements DetectorInterface
{
    public function detect(): DetectionResult
    {
        $process = new Process(['php', '-v']);
        $process->run();

        $output = $process->getOutput();

        if (!$process->isSuccessful()) {
            return new DetectionResult(
                name: 'PHP',
                installed: false,
            );
        }

        $version = $this->parseVersion($output);
        $extensions = $this->detectExtensions();

        return new DetectionResult(
            name: 'PHP',
            installed: true,
            version: $version,
            status: 'installed',
            details: [
                'extensions' => $extensions,
                'raw_output' => trim($output),
            ],
        );
    }

    private function parseVersion(string $output): ?string
    {
        if (preg_match('/PHP (\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function detectExtensions(): array
    {
        $process = new Process(['php', '-m']);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $output = $process->getOutput();
        $lines = array_filter(
            array_map('trim', explode("\n", $output)),
            fn(string $line): bool => $line !== '' && !str_starts_with($line, '['),
        );

        return array_values($lines);
    }
}
