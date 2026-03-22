<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Storage;

use Generator;
use JsonException;
use RuntimeException;

final class NdjsonReader
{
    /**
     * Read all entries from an NDJSON file.
     *
     * @param callable(array<string, mixed>): bool|null $filter
     * @return Generator<int, array<string, mixed>>
     */
    public function read(string $filePath, ?callable $filter = null): Generator
    {
        if (!file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open file for reading: %s', $filePath));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                try {
                    /** @var array<string, mixed> $entry */
                    $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    // Skip corrupted lines
                    continue;
                }

                if ($filter !== null && !$filter($entry)) {
                    continue;
                }

                yield $entry;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read entries with pagination.
     *
     * @param callable(array<string, mixed>): bool|null $filter
     * @return list<array<string, mixed>>
     */
    public function readPaginated(
        string $filePath,
        int $offset = 0,
        int $limit = 50,
        ?callable $filter = null,
    ): array {
        $entries = [];
        $skipped = 0;
        $taken = 0;

        foreach ($this->read($filePath, $filter) as $entry) {
            if ($skipped < $offset) {
                $skipped++;
                continue;
            }

            if ($taken >= $limit) {
                break;
            }

            $entries[] = $entry;
            $taken++;
        }

        return $entries;
    }

    /**
     * Read entries in reverse order (newest first).
     *
     * @param callable(array<string, mixed>): bool|null $filter
     * @return list<array<string, mixed>>
     */
    public function readReverse(
        string $filePath,
        int $limit = 50,
        ?callable $filter = null,
    ): array {
        if (!file_exists($filePath)) {
            return [];
        }

        $allLines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($allLines === false) {
            return [];
        }

        $entries = [];
        $taken = 0;

        for ($i = count($allLines) - 1; $i >= 0 && $taken < $limit; $i--) {
            $line = trim($allLines[$i]);
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $entry */
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if ($filter !== null && !$filter($entry)) {
                continue;
            }

            $entries[] = $entry;
            $taken++;
        }

        return $entries;
    }

    /**
     * Read entries from multiple files.
     *
     * @param list<string> $filePaths
     * @param callable(array<string, mixed>): bool|null $filter
     * @return Generator<int, array<string, mixed>>
     */
    public function readMultiple(array $filePaths, ?callable $filter = null): Generator
    {
        foreach ($filePaths as $filePath) {
            yield from $this->read($filePath, $filter);
        }
    }

    /**
     * Count entries in a file.
     *
     * @param callable(array<string, mixed>): bool|null $filter
     */
    public function count(string $filePath, ?callable $filter = null): int
    {
        $count = 0;
        foreach ($this->read($filePath, $filter) as $_entry) {
            $count++;
        }

        return $count;
    }
}
