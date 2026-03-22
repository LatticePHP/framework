<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console;

final class Application
{
    public function __construct(
        private readonly CommandRegistry $registry,
    ) {}

    /** @param string[] $argv */
    public function run(array $argv): int
    {
        $input = new Input($argv);
        $output = new Output();

        $commandName = $input->getCommand();

        if ($commandName === '' || $commandName === 'list') {
            return $this->listCommands($output);
        }

        $command = $this->registry->find($commandName);

        if ($command === null) {
            $output->error("Command [{$commandName}] not found.");
            echo $output->flush() . PHP_EOL;
            return 1;
        }

        $command->setInput($input);

        $exitCode = $command->handle($input, $output);

        $flushed = $output->flush();
        if ($flushed !== '') {
            echo $flushed . PHP_EOL;
        }

        return $exitCode;
    }

    private function listCommands(Output $output): int
    {
        $output->info('Available commands:');
        $output->line('');

        $commands = $this->registry->all();

        if ($commands === []) {
            $output->line('  No commands registered.');
        } else {
            $rows = [];
            foreach ($commands as $name => $command) {
                $rows[] = [$name, $command->description()];
            }
            $output->table(['Command', 'Description'], $rows);
        }

        echo $output->flush() . PHP_EOL;
        return 0;
    }
}
