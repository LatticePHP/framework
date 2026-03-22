<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit\Console;

use Lattice\DevTools\Console\Application;
use Lattice\DevTools\Console\Command;
use Lattice\DevTools\Console\CommandRegistry;
use Lattice\DevTools\Console\Input;
use Lattice\DevTools\Console\Output;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function test_run_executes_matching_command(): void
    {
        $registry = new CommandRegistry();
        $executed = false;

        $command = new class($executed) extends Command {
            private bool $ref;

            public function __construct(private bool &$executedRef)
            {
                $this->ref = &$executedRef;
            }

            public function name(): string
            {
                return 'test:run';
            }

            public function description(): string
            {
                return 'A test command';
            }

            public function handle(Input $input, Output $output): int
            {
                $this->executedRef = true;
                return 0;
            }
        };

        $registry->register($command);
        $app = new Application($registry);

        ob_start();
        $exitCode = $app->run(['lattice', 'test:run']);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
        $this->assertTrue($executed);
    }

    public function test_run_returns_1_for_unknown_command(): void
    {
        $registry = new CommandRegistry();
        $app = new Application($registry);

        ob_start();
        $exitCode = $app->run(['lattice', 'unknown:cmd']);
        ob_end_clean();

        $this->assertSame(1, $exitCode);
    }

    public function test_run_lists_commands_when_no_command_given(): void
    {
        $registry = new CommandRegistry();
        $command = new class extends Command {
            public function name(): string { return 'test:cmd'; }
            public function description(): string { return 'Test description'; }
            public function handle(Input $input, Output $output): int { return 0; }
        };

        $registry->register($command);
        $app = new Application($registry);

        ob_start();
        $exitCode = $app->run(['lattice']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('test:cmd', $output);
        $this->assertStringContainsString('Test description', $output);
    }

    public function test_run_list_command_explicitly(): void
    {
        $registry = new CommandRegistry();
        $app = new Application($registry);

        ob_start();
        $exitCode = $app->run(['lattice', 'list']);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
    }

    public function test_command_receives_correct_input(): void
    {
        $registry = new CommandRegistry();
        $receivedArg = null;

        $command = new class($receivedArg) extends Command {
            public function __construct(private ?string &$ref) {}

            public function name(): string { return 'test:echo'; }
            public function description(): string { return 'Echo test'; }

            public function handle(Input $input, Output $output): int
            {
                $this->ref = $input->getArgument('0');
                return 0;
            }
        };

        $registry->register($command);
        $app = new Application($registry);

        ob_start();
        $app->run(['lattice', 'test:echo', 'hello']);
        ob_end_clean();

        $this->assertSame('hello', $receivedArg);
    }

    public function test_command_exit_code_propagates(): void
    {
        $registry = new CommandRegistry();

        $command = new class extends Command {
            public function name(): string { return 'test:fail'; }
            public function description(): string { return 'Fail test'; }

            public function handle(Input $input, Output $output): int
            {
                return 42;
            }
        };

        $registry->register($command);
        $app = new Application($registry);

        ob_start();
        $exitCode = $app->run(['lattice', 'test:fail']);
        ob_end_clean();

        $this->assertSame(42, $exitCode);
    }
}
