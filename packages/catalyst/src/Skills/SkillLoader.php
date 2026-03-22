<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Skills;

final class SkillLoader
{
    private readonly SkillRegistry $registry;

    public function __construct(?SkillRegistry $registry = null)
    {
        $this->registry = $registry ?? new SkillRegistry();
    }

    public function getRegistry(): SkillRegistry
    {
        return $this->registry;
    }

    /**
     * Load all skills: bundled, third-party, and project-level.
     * Project-level skills take precedence over bundled ones.
     */
    public function loadAll(string $basePath): void
    {
        // 1. Load bundled skills
        $this->loadFromDirectory(
            dirname(__DIR__, 2) . '/resources/skills',
            'bundled',
        );

        // 2. Load project-level skills (override bundled)
        $this->loadFromDirectory(
            $basePath . '/.ai/skills',
            'project',
        );
    }

    /**
     * Load skills from a directory structure.
     * Expected: {directory}/{skill-name}/SKILL.md
     */
    public function loadFromDirectory(string $directory, string $source): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $skillDir = $directory . '/' . $entry;
            $skillFile = $skillDir . '/SKILL.md';

            if (!is_file($skillFile)) {
                continue;
            }

            $parsed = $this->parseSkillFile($skillFile);

            if ($parsed === null) {
                continue;
            }

            $this->registry->register(
                name: $parsed['name'],
                description: $parsed['description'],
                triggers: $parsed['triggers'],
                content: $parsed['content'],
                source: $source,
            );
        }
    }

    /**
     * Parse a SKILL.md file with YAML frontmatter.
     *
     * @return array{name: string, description: string, triggers: list<string>, content: string}|null
     */
    public function parseSkillFile(string $path): ?array
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        // Normalize line endings
        $raw = str_replace("\r\n", "\n", $raw);

        // Check for YAML frontmatter delimiters
        if (!str_starts_with($raw, "---\n")) {
            return null;
        }

        $endPos = strpos($raw, "\n---\n", 4);

        if ($endPos === false) {
            return null;
        }

        $frontmatter = substr($raw, 4, $endPos - 4);
        $content = trim(substr($raw, $endPos + 5));

        // Simple YAML parser for frontmatter (name, description, triggers)
        $parsed = $this->parseSimpleYaml($frontmatter);

        $name = $parsed['name'] ?? null;
        $description = $parsed['description'] ?? null;
        $triggers = $parsed['triggers'] ?? [];

        if ($name === null || $description === null) {
            return null;
        }

        return [
            'name' => $name,
            'description' => $description,
            'triggers' => is_array($triggers) ? $triggers : [$triggers],
            'content' => $content,
        ];
    }

    /**
     * Minimal YAML frontmatter parser.
     * Supports: key: value and key: [item1, item2] and list items with - prefix.
     *
     * @return array<string, mixed>
     */
    private function parseSimpleYaml(string $yaml): array
    {
        $result = [];
        $currentKey = null;
        $lines = explode("\n", $yaml);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // List item under current key
            if (str_starts_with($trimmed, '- ') && $currentKey !== null) {
                if (!isset($result[$currentKey]) || !is_array($result[$currentKey])) {
                    $result[$currentKey] = [];
                }
                $result[$currentKey][] = trim(substr($trimmed, 2));
                continue;
            }

            // Key: value pair
            $colonPos = strpos($trimmed, ':');

            if ($colonPos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $colonPos));
            $value = trim(substr($trimmed, $colonPos + 1));

            $currentKey = $key;

            if ($value === '') {
                // Value will follow as list items
                $result[$key] = [];
                continue;
            }

            // Inline array: [item1, item2]
            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $inner = substr($value, 1, -1);
                $result[$key] = array_map('trim', explode(',', $inner));
                continue;
            }

            // Strip quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
