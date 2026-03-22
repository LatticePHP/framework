<?php

declare(strict_types=1);

namespace Lattice\Prism\Storage;

use Lattice\Prism\Event\ErrorEvent;

interface StorageInterface
{
    /**
     * Store an error event, returning the blob path and byte offset.
     *
     * @return array{blob_path: string, byte_offset: int}
     */
    public function store(ErrorEvent $event): array;

    /**
     * Retrieve raw NDJSON lines from a blob path starting at the given offset.
     *
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $blobPath, int $offset = 0, int $limit = 50): array;
}
