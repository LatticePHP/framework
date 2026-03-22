<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Storage;

use JsonException;
use RuntimeException;

final class NdjsonWriter
{
    /**
     * @param array<string, mixed> $entry
     */
    public function write(string $filePath, array $entry): void
    {
        $this->writeBatch($filePath, [$entry]);
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    public function writeBatch(string $filePath, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
            }
        }

        $lines = '';
        foreach ($entries as $entry) {
            try {
                $lines .= json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            } catch (JsonException $e) {
                throw new RuntimeException(sprintf('Failed to encode entry to JSON: %s', $e->getMessage()), 0, $e);
            }
        }

        $handle = fopen($filePath, 'a');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open file for writing: %s', $filePath));
        }

        try {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $lines);
                fflush($handle);
                flock($handle, LOCK_UN);
            } else {
                throw new RuntimeException(sprintf('Failed to acquire lock on file: %s', $filePath));
            }
        } finally {
            fclose($handle);
        }
    }
}
