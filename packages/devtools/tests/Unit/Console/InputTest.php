<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit\Console;

use Lattice\DevTools\Console\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function test_get_command_from_argv(): void
    {
        $input = new Input(['lattice', 'make:module']);

        $this->assertSame('make:module', $input->getCommand());
    }

    public function test_get_command_empty_when_no_command(): void
    {
        $input = new Input(['lattice']);

        $this->assertSame('', $input->getCommand());
    }

    public function test_get_positional_argument(): void
    {
        $input = new Input(['lattice', 'make:module', 'UserModule']);

        $this->assertSame('UserModule', $input->getArgument('0'));
    }

    public function test_get_argument_returns_null_for_missing(): void
    {
        $input = new Input(['lattice', 'make:module']);

        $this->assertNull($input->getArgument('0'));
    }

    public function test_get_option_with_equals(): void
    {
        $input = new Input(['lattice', 'make:module', '--name=UserModule']);

        $this->assertSame('UserModule', $input->getOption('name'));
    }

    public function test_get_option_with_space(): void
    {
        $input = new Input(['lattice', 'make:module', '--name', 'UserModule']);

        $this->assertSame('UserModule', $input->getOption('name'));
    }

    public function test_boolean_flag_option(): void
    {
        $input = new Input(['lattice', 'make:module', '--verbose']);

        $this->assertTrue($input->getOption('verbose'));
    }

    public function test_short_flag(): void
    {
        $input = new Input(['lattice', 'make:module', '-v']);

        $this->assertTrue($input->getOption('v'));
    }

    public function test_has_option(): void
    {
        $input = new Input(['lattice', 'make:module', '--force']);

        $this->assertTrue($input->hasOption('force'));
        $this->assertFalse($input->hasOption('quiet'));
    }

    public function test_get_option_returns_null_for_missing(): void
    {
        $input = new Input(['lattice', 'make:module']);

        $this->assertNull($input->getOption('missing'));
    }

    public function test_mixed_arguments_and_options(): void
    {
        $input = new Input(['lattice', 'make:module', 'UserModule', '--force', '--path=/app']);

        $this->assertSame('make:module', $input->getCommand());
        $this->assertSame('UserModule', $input->getArgument('0'));
        $this->assertTrue($input->getOption('force'));
        $this->assertSame('/app', $input->getOption('path'));
    }

    public function test_get_all_arguments(): void
    {
        $input = new Input(['lattice', 'cmd', 'arg1', 'arg2']);

        $this->assertSame(['0' => 'arg1', '1' => 'arg2'], $input->getArguments());
    }

    public function test_get_all_options(): void
    {
        $input = new Input(['lattice', 'cmd', '--foo=bar', '--baz']);

        $this->assertSame(['foo' => 'bar', 'baz' => true], $input->getOptions());
    }
}
