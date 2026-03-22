<?php

declare(strict_types=1);

namespace Lattice\Filesystem;

interface FilesystemInterface
{
    public function read(string $path): string;

    public function write(string $path, string $contents): void;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    public function copy(string $source, string $destination): void;

    public function move(string $source, string $destination): void;

    /** @return string[] */
    public function listContents(string $directory): array;

    public function createDirectory(string $path): void;

    public function deleteDirectory(string $path): bool;

    public function lastModified(string $path): int;

    public function fileSize(string $path): int;

    public function mimeType(string $path): string;

    public function url(string $path): string;

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string;
}
