<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Driver;

use Lattice\Filesystem\FilesystemInterface;

final class InMemoryFilesystem implements FilesystemInterface
{
    /** @var array<string, array{contents: string, modified: int}> */
    private array $files = [];

    /** @var array<string, true> */
    private array $directories = [];

    /** @var array<string, string> extension => mime type */
    private const MIME_TYPES = [
        'txt' => 'text/plain',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'csv' => 'text/csv',
    ];

    public function read(string $path): string
    {
        $path = $this->normalizePath($path);

        if (!isset($this->files[$path])) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return $this->files[$path]['contents'];
    }

    public function write(string $path, string $contents): void
    {
        $path = $this->normalizePath($path);

        $this->files[$path] = [
            'contents' => $contents,
            'modified' => time(),
        ];

        // Ensure parent directories exist
        $dir = dirname($path);
        while ($dir !== '' && $dir !== '.') {
            $this->directories[$dir] = true;
            $dir = dirname($dir);
        }
    }

    public function exists(string $path): bool
    {
        $path = $this->normalizePath($path);

        return isset($this->files[$path]) || isset($this->directories[$path]);
    }

    public function delete(string $path): bool
    {
        $path = $this->normalizePath($path);

        if (!isset($this->files[$path])) {
            return false;
        }

        unset($this->files[$path]);

        return true;
    }

    public function copy(string $source, string $destination): void
    {
        $source = $this->normalizePath($source);
        $destination = $this->normalizePath($destination);

        if (!isset($this->files[$source])) {
            throw new \RuntimeException("Source file not found: {$source}");
        }

        $this->files[$destination] = $this->files[$source];
        $this->files[$destination]['modified'] = time();

        // Ensure parent directories for destination
        $dir = dirname($destination);
        while ($dir !== '' && $dir !== '.') {
            $this->directories[$dir] = true;
            $dir = dirname($dir);
        }
    }

    public function move(string $source, string $destination): void
    {
        $this->copy($source, $destination);
        $this->delete($source);
    }

    public function listContents(string $directory): array
    {
        $directory = $this->normalizePath($directory);
        $prefix = $directory === '' ? '' : $directory . '/';
        $result = [];

        // Collect direct child files
        foreach ($this->files as $path => $_) {
            if ($prefix === '') {
                // Root listing: only top-level items
                if (!str_contains($path, '/')) {
                    $result[$path] = true;
                } else {
                    $topDir = explode('/', $path)[0];
                    $result[$topDir] = true;
                }
            } elseif (str_starts_with($path, $prefix)) {
                $relative = substr($path, strlen($prefix));
                if (!str_contains($relative, '/')) {
                    $result[$relative] = true;
                } else {
                    $topDir = explode('/', $relative)[0];
                    $result[$topDir] = true;
                }
            }
        }

        // Collect direct child directories
        foreach ($this->directories as $dirPath => $_) {
            if ($prefix === '') {
                if (!str_contains($dirPath, '/')) {
                    $result[$dirPath] = true;
                }
            } elseif (str_starts_with($dirPath, $prefix)) {
                $relative = substr($dirPath, strlen($prefix));
                if (!str_contains($relative, '/')) {
                    $result[$relative] = true;
                }
            }
        }

        return array_keys($result);
    }

    public function createDirectory(string $path): void
    {
        $path = $this->normalizePath($path);
        $this->directories[$path] = true;

        $dir = dirname($path);
        while ($dir !== '' && $dir !== '.') {
            $this->directories[$dir] = true;
            $dir = dirname($dir);
        }
    }

    public function deleteDirectory(string $path): bool
    {
        $path = $this->normalizePath($path);

        if (!isset($this->directories[$path])) {
            return false;
        }

        $prefix = $path . '/';

        // Remove all files within the directory
        foreach (array_keys($this->files) as $filePath) {
            if (str_starts_with($filePath, $prefix)) {
                unset($this->files[$filePath]);
            }
        }

        // Remove all subdirectories
        foreach (array_keys($this->directories) as $dirPath) {
            if ($dirPath === $path || str_starts_with($dirPath, $prefix)) {
                unset($this->directories[$dirPath]);
            }
        }

        return true;
    }

    public function lastModified(string $path): int
    {
        $path = $this->normalizePath($path);

        if (!isset($this->files[$path])) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return $this->files[$path]['modified'];
    }

    public function fileSize(string $path): int
    {
        $path = $this->normalizePath($path);

        if (!isset($this->files[$path])) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return strlen($this->files[$path]['contents']);
    }

    public function mimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$ext] ?? 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return $this->normalizePath($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        throw new \RuntimeException('Temporary URLs are not supported by the in-memory filesystem driver.');
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }
}
