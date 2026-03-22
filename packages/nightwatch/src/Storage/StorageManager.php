<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use Lattice\Nightwatch\Entry;

final class StorageManager
{
    private readonly NdjsonWriter $writer;
    private readonly NdjsonReader $reader;
    private readonly TimePartitioner $partitioner;

    public function __construct(
        private readonly string $basePath,
        ?NdjsonWriter $writer = null,
        ?NdjsonReader $reader = null,
        ?TimePartitioner $partitioner = null,
    ) {
        $this->writer = $writer ?? new NdjsonWriter();
        $this->reader = $reader ?? new NdjsonReader();
        $this->partitioner = $partitioner ?? new TimePartitioner($this->basePath);
    }

    public function store(Entry $entry): void
    {
        $path = $this->partitioner->pathForEntry($entry->type->value, $entry->timestamp);
        $this->writer->write($path, $entry->jsonSerialize());
    }

    /**
     * @param list<Entry> $entries
     */
    public function storeBatch(array $entries): void
    {
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($entries as $entry) {
            $path = $this->partitioner->pathForEntry($entry->type->value, $entry->timestamp);
            $grouped[$path][] = $entry->jsonSerialize();
        }

        foreach ($grouped as $path => $pathEntries) {
            $this->writer->writeBatch($path, $pathEntries);
        }
    }

    /**
     * Query entries across time-partitioned files.
     *
     * @param callable(array<string, mixed>): bool|null $filter
     * @return list<array<string, mixed>>
     */
    public function query(
        string $type,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?callable $filter = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $paths = $this->partitioner->pathsForRange($from, $to, $type);
        $entries = [];
        $skipped = 0;
        $taken = 0;

        foreach ($paths as $path) {
            if ($taken >= $limit) {
                break;
            }

            foreach ($this->reader->read($path, $filter) as $entry) {
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
        }

        return $entries;
    }

    /**
     * Get the most recent entries of a given type.
     *
     * @return list<array<string, mixed>>
     */
    public function queryLatest(string $type, int $count = 20): array
    {
        $now = new DateTimeImmutable();
        $lookback = $now->modify('-24 hours');

        $paths = $this->partitioner->pathsForRange($lookback, $now, $type);
        $paths = array_reverse($paths);

        $entries = [];
        foreach ($paths as $path) {
            if (count($entries) >= $count) {
                break;
            }

            $remaining = $count - count($entries);
            $batch = $this->reader->readReverse($path, $remaining);
            $entries = array_merge($entries, $batch);
        }

        return array_slice($entries, 0, $count);
    }

    public function getPartitioner(): TimePartitioner
    {
        return $this->partitioner;
    }

    public function getReader(): NdjsonReader
    {
        return $this->reader;
    }

    public function getWriter(): NdjsonWriter
    {
        return $this->writer;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
