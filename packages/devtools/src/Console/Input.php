<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console;

final class Input
{
    private readonly string $command;

    /** @var array<string, string> */
    private readonly array $arguments;

    /** @var array<string, mixed> */
    private readonly array $options;

    /** @param string[] $argv */
    public function __construct(array $argv)
    {
        $args = array_values($argv);

        // First element is script name, second is command
        $this->command = $args[1] ?? '';

        $arguments = [];
        $options = [];
        $positionalIndex = 0;

        for ($i = 2, $count = count($args); $i < $count; $i++) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                    $options[$key] = $value;
                } else {
                    // Check if next arg is the value
                    if (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                        $options[$option] = $args[++$i];
                    } else {
                        $options[$option] = true;
                    }
                }
            } elseif (str_starts_with($arg, '-')) {
                $flag = substr($arg, 1);
                $options[$flag] = true;
            } else {
                $arguments[(string) $positionalIndex] = $arg;
                $positionalIndex++;
            }
        }

        $this->arguments = $arguments;
        $this->options = $options;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getArgument(string $name): ?string
    {
        return $this->arguments[$name] ?? null;
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /** @return array<string, string> */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }
}
