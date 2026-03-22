<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Driver;

use Lattice\Filesystem\FilesystemInterface;

final class LocalFilesystem implements FilesystemInterface
{
    public function __construct(
        private readonly string $baseDir,
    ) {}

    public function read(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return file_get_contents($fullPath);
    }

    public function write(string $path, string $contents): void
    {
        $fullPath = $this->resolvePath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $contents);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    public function copy(string $source, string $destination): void
    {
        $sourcePath = $this->resolvePath($source);
        $destPath = $this->resolvePath($destination);

        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!copy($sourcePath, $destPath)) {
            throw new \RuntimeException("Failed to copy {$source} to {$destination}");
        }
    }

    public function move(string $source, string $destination): void
    {
        $sourcePath = $this->resolvePath($source);
        $destPath = $this->resolvePath($destination);

        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!rename($sourcePath, $destPath)) {
            throw new \RuntimeException("Failed to move {$source} to {$destination}");
        }
    }

    public function listContents(string $directory): array
    {
        $fullPath = $this->resolvePath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $items = scandir($fullPath);
        $result = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $result[] = $item;
        }

        return $result;
    }

    public function createDirectory(string $path): void
    {
        $fullPath = $this->resolvePath($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->resolvePath($path);

        if (!is_dir($fullPath)) {
            return false;
        }

        $this->removeDirectoryRecursive($fullPath);

        return true;
    }

    public function lastModified(string $path): int
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return filemtime($fullPath);
    }

    public function fileSize(string $path): int
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return filesize($fullPath);
    }

    public function mimeType(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $mime = mime_content_type($fullPath);

        return $mime !== false ? $mime : 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return $path;
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        throw new \RuntimeException('Temporary URLs are not supported by the local filesystem driver.');
    }

    private function resolvePath(string $path): string
    {
        // Prevent path traversal
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException("Path traversal is not allowed: {$path}");
        }

        $path = ltrim($path, '/\\');

        if ($path === '') {
            return $this->baseDir;
        }

        return $this->baseDir . '/' . $path;
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
