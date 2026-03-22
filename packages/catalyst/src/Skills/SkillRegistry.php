<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Skills;

final class SkillRegistry
{
    /**
     * @var array<string, array{name: string, description: string, triggers: list<string>, content: string, source: string}>
     */
    private array $skills = [];

    /**
     * @param list<string> $triggers
     */
    public function register(
        string $name,
        string $description,
        array $triggers,
        string $content,
        string $source,
    ): void {
        $this->skills[$name] = [
            'name' => $name,
            'description' => $description,
            'triggers' => $triggers,
            'content' => $content,
            'source' => $source,
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->skills[$name]);
    }

    /**
     * @return array{name: string, description: string, triggers: list<string>, content: string, source: string}|null
     */
    public function get(string $name): ?array
    {
        return $this->skills[$name] ?? null;
    }

    /**
     * Find a skill by trigger keyword.
     *
     * @return array{name: string, description: string, triggers: list<string>, content: string, source: string}|null
     */
    public function findByTrigger(string $trigger): ?array
    {
        foreach ($this->skills as $skill) {
            if (in_array($trigger, $skill['triggers'], true)) {
                return $skill;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{name: string, description: string, triggers: list<string>, content: string, source: string}>
     */
    public function all(): array
    {
        return $this->skills;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->skills);
    }

    public function count(): int
    {
        return count($this->skills);
    }
}
