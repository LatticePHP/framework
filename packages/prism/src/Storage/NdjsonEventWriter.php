<?php

declare(strict_types=1);

namespace Lattice\Prism\Storage;

use JsonException;
use Lattice\Prism\Event\ErrorEvent;
use RuntimeException;

final class NdjsonEventWriter
{
    /**
     * Append an ErrorEvent as a single NDJSON line to the given file.
     * Returns the byte offset at which the line was written.
     */
    public function append(string $filePath, ErrorEvent $event): int
    {
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
            }
        }

        try {
            $line = json_encode($event->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to encode event to JSON: %s', $e->getMessage()), 0, $e);
        }

        $handle = fopen($filePath, 'a+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open file for writing: %s', $filePath));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException(sprintf('Failed to acquire lock on file: %s', $filePath));
            }

            // Get current file size (this is where our line will start)
            fseek($handle, 0, SEEK_END);
            $offset = (int) ftell($handle);

            fwrite($handle, $line);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $offset;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Append a raw array as a single NDJSON line.
     *
     * @param array<string, mixed> $data
     */
    public function appendRaw(string $filePath, array $data): int
    {
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
            }
        }

        try {
            $line = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to encode data to JSON: %s', $e->getMessage()), 0, $e);
        }

        $handle = fopen($filePath, 'a+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open file for writing: %s', $filePath));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException(sprintf('Failed to acquire lock on file: %s', $filePath));
            }

            fseek($handle, 0, SEEK_END);
            $offset = (int) ftell($handle);

            fwrite($handle, $line);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $offset;
        } finally {
            fclose($handle);
        }
    }
}
