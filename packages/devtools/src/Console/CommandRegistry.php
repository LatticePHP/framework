<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console;

final class CommandRegistry
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function register(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    public function find(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    /** @return array<string, Command> */
    public function all(): array
    {
        return $this->commands;
    }
}
