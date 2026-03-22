<?php

declare(strict_types=1);

namespace Lattice\Prism\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Lattice\Prism\Event\ErrorEvent;
use RuntimeException;

final class LocalFilesystemStorage implements StorageInterface
{
    private readonly NdjsonEventWriter $writer;

    public function __construct(
        private readonly string $basePath,
        ?NdjsonEventWriter $writer = null,
    ) {
        $this->writer = $writer ?? new NdjsonEventWriter();
    }

    /**
     * Store an error event in the filesystem using time-partitioned NDJSON.
     * Path layout: {basePath}/{year}/{month}/{day}/{hour}/{project_id}/{environment}/events.ndjson
     *
     * @return array{blob_path: string, byte_offset: int}
     */
    public function store(ErrorEvent $event): array
    {
        $blobPath = $this->buildPath($event->projectId, $event->environment, $event->timestamp);
        $fullPath = $this->basePath . '/' . $blobPath;

        $offset = $this->writer->append($fullPath, $event);

        return [
            'blob_path' => $blobPath,
            'byte_offset' => $offset,
        ];
    }

    /**
     * Retrieve events from a blob file.
     *
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $blobPath, int $offset = 0, int $limit = 50): array
    {
        $fullPath = $this->basePath . '/' . $blobPath;

        if (!file_exists($fullPath)) {
            return [];
        }

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open file for reading: %s', $fullPath));
        }

        try {
            // Seek to byte offset
            if ($offset > 0) {
                fseek($handle, $offset);
            }

            $entries = [];
            $taken = 0;

            while ($taken < $limit && ($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                try {
                    /** @var array<string, mixed> $entry */
                    $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $entries[] = $entry;
                    $taken++;
                } catch (JsonException) {
                    // Skip corrupted lines
                    continue;
                }
            }

            return $entries;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Build the relative blob path for a given project, environment, and timestamp.
     */
    public function buildPath(string $projectId, string $environment, DateTimeInterface $timestamp): string
    {
        return sprintf(
            '%s/%s/%s/events.ndjson',
            $timestamp->format('Y/m/d/H'),
            $projectId,
            $environment,
        );
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
