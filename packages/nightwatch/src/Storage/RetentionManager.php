<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Storage;

use DateTimeImmutable;
use Lattice\Nightwatch\Config\NightwatchConfig;
use Lattice\Nightwatch\EntryType;
use RuntimeException;

final class RetentionManager
{
    private readonly TimePartitioner $partitioner;

    public function __construct(
        private readonly NightwatchConfig $config,
        ?TimePartitioner $partitioner = null,
        private readonly ?string $appEnv = null,
    ) {
        $this->partitioner = $partitioner ?? new TimePartitioner($this->config->storagePath);
    }

    /**
     * Prune directories older than the configured TTL for a specific type.
     *
     * @return int Number of directories deleted
     */
    public function prune(string $type): int
    {
        $retentionDays = $this->config->retentionDays($this->appEnv);
        $cutoff = new DateTimeImmutable(sprintf('-%d days', $retentionDays));

        $paths = $this->partitioner->directoryPathsOlderThan($cutoff, $type);
        $deleted = 0;

        foreach ($paths as $path) {
            if ($this->deleteDirectory($path)) {
                $deleted++;
                $this->cleanEmptyParents($path);
            }
        }

        return $deleted;
    }

    /**
     * Prune all entry types.
     *
     * @return array<string, int> Map of type => directories deleted
     */
    public function pruneAll(): array
    {
        $summary = [];

        foreach (EntryType::cases() as $type) {
            $count = $this->prune($type->value);
            if ($count > 0) {
                $summary[$type->value] = $count;
            }
        }

        return $summary;
    }

    private function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;

            if (is_dir($entryPath)) {
                $this->deleteDirectory($entryPath);
            } else {
                unlink($entryPath);
            }
        }

        return rmdir($path);
    }

    private function cleanEmptyParents(string $path): void
    {
        $parent = dirname($path);
        $basePath = $this->config->storagePath;

        while ($parent !== $basePath && str_starts_with($parent, $basePath)) {
            $entries = scandir($parent);
            if ($entries === false) {
                break;
            }

            $nonDot = array_filter($entries, fn(string $e) => $e !== '.' && $e !== '..');

            if (count($nonDot) > 0) {
                break;
            }

            rmdir($parent);
            $parent = dirname($parent);
        }
    }
}
