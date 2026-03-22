<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console;

abstract class Command
{
    protected ?Input $input = null;

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function handle(Input $input, Output $output): int;

    public function setInput(Input $input): void
    {
        $this->input = $input;
    }

    protected function argument(string $name): ?string
    {
        return $this->input?->getArgument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input?->getOption($name);
    }
}
