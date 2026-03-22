<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Command;

use Lattice\DevTools\Console\Command as BaseCommand;
use Lattice\DevTools\Console\Input;
use Lattice\DevTools\Console\Output;
use Lattice\DevTools\GeneratorManager;

final class GenerateCommand extends BaseCommand
{
    public function __construct(
        private readonly GeneratorManager $generatorManager,
        private readonly string $generatorName,
    ) {}

    public function name(): string
    {
        return "make:{$this->generatorName}";
    }

    public function description(): string
    {
        return "Generate a new {$this->generatorName}";
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->getArgument('0');

        if ($name === null) {
            $output->error("Please provide a name for the {$this->generatorName}.");
            return 1;
        }

        try {
            $files = $this->generatorManager->generate($this->generatorName, [
                'name' => $name,
            ]);

            foreach ($files as $file) {
                $output->success("Created: {$file->path}");
            }

            return 0;
        } catch (\InvalidArgumentException $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }
}
