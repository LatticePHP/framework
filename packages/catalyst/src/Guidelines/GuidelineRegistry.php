<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Guidelines;

final class GuidelineRegistry
{
    /** @var array<string, string> package name => guideline content */
    private array $guidelines = [];

    /** @var array<string, string> package name => file path */
    private array $paths = [];

    public function register(string $packageName, string $content, string $path = ''): void
    {
        $this->guidelines[$packageName] = $content;
        $this->paths[$packageName] = $path;
    }

    public function has(string $packageName): bool
    {
        return isset($this->guidelines[$packageName]);
    }

    public function get(string $packageName): ?string
    {
        return $this->guidelines[$packageName] ?? null;
    }

    public function getPath(string $packageName): ?string
    {
        return $this->paths[$packageName] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->guidelines;
    }

    /**
     * @return list<string>
     */
    public function packages(): array
    {
        return array_keys($this->guidelines);
    }

    public function count(): int
    {
        return count($this->guidelines);
    }

    /**
     * Load guidelines from a directory of .md files.
     * Each file should be named {package-name}.md (e.g., core.md, routing.md).
     */
    public function loadFromDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.md');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $packageName = basename($file, '.md');
            $content = file_get_contents($file);

            if ($content !== false) {
                $this->register($packageName, $content, $file);
            }
        }
    }
}
