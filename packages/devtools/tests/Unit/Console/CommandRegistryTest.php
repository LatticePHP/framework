<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit\Console;

use Lattice\DevTools\Console\Command;
use Lattice\DevTools\Console\CommandRegistry;
use Lattice\DevTools\Console\Input;
use Lattice\DevTools\Console\Output;
use PHPUnit\Framework\TestCase;

final class CommandRegistryTest extends TestCase
{
    public function test_register_and_find_command(): void
    {
        $registry = new CommandRegistry();
        $command = $this->createTestCommand('test:hello');

        $registry->register($command);

        $this->assertSame($command, $registry->find('test:hello'));
    }

    public function test_find_returns_null_for_unregistered(): void
    {
        $registry = new CommandRegistry();

        $this->assertNull($registry->find('nonexistent'));
    }

    public function test_all_returns_registered_commands(): void
    {
        $registry = new CommandRegistry();
        $cmd1 = $this->createTestCommand('cmd:one');
        $cmd2 = $this->createTestCommand('cmd:two');

        $registry->register($cmd1);
        $registry->register($cmd2);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('cmd:one', $all);
        $this->assertArrayHasKey('cmd:two', $all);
    }

    public function test_all_returns_empty_when_none_registered(): void
    {
        $registry = new CommandRegistry();

        $this->assertSame([], $registry->all());
    }

    public function test_register_overwrites_existing_command(): void
    {
        $registry = new CommandRegistry();
        $cmd1 = $this->createTestCommand('test:cmd');
        $cmd2 = $this->createTestCommand('test:cmd');

        $registry->register($cmd1);
        $registry->register($cmd2);

        $this->assertSame($cmd2, $registry->find('test:cmd'));
    }

    private function createTestCommand(string $name): Command
    {
        return new class($name) extends Command {
            public function __construct(private readonly string $cmdName) {}

            public function name(): string
            {
                return $this->cmdName;
            }

            public function description(): string
            {
                return "Test command: {$this->cmdName}";
            }

            public function handle(Input $input, Output $output): int
            {
                return 0;
            }
        };
    }
}
