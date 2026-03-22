<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

final class DetectionResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $installed,
        public readonly ?string $version = null,
        public readonly string $status = 'unknown',
        public readonly array $details = [],
    ) {
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'running' => 'green',
            'stopped' => 'red',
            'installed' => 'yellow',
            default => 'gray',
        };
    }

    public function getStatusLabel(): string
    {
        if (!$this->installed) {
            return 'Not installed';
        }

        return match ($this->status) {
            'running' => 'Running',
            'stopped' => 'Stopped',
            'installed' => 'Installed',
            default => 'Unknown',
        };
    }
}
