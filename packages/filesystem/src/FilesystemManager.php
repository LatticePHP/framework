<?php

declare(strict_types=1);

namespace Lattice\Filesystem;

final class FilesystemManager
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    public function disk(string $name = 'default'): FilesystemInterface
    {
        if (!isset($this->disks[$name])) {
            throw new \InvalidArgumentException("Filesystem disk [{$name}] is not configured.");
        }

        return $this->disks[$name];
    }

    public function addDisk(string $name, FilesystemInterface $driver): void
    {
        $this->disks[$name] = $driver;
    }
}
