<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit\Console;

use Lattice\DevTools\Console\Output;
use PHPUnit\Framework\TestCase;

final class OutputTest extends TestCase
{
    public function test_info_message(): void
    {
        $output = new Output();
        $output->info('Hello');

        $this->assertSame(['[INFO] Hello'], $output->getBuffer());
    }

    public function test_error_message(): void
    {
        $output = new Output();
        $output->error('Something broke');

        $this->assertSame(['[ERROR] Something broke'], $output->getBuffer());
    }

    public function test_success_message(): void
    {
        $output = new Output();
        $output->success('Done');

        $this->assertSame(['[SUCCESS] Done'], $output->getBuffer());
    }

    public function test_warning_message(): void
    {
        $output = new Output();
        $output->warning('Careful');

        $this->assertSame(['[WARNING] Careful'], $output->getBuffer());
    }

    public function test_line_message(): void
    {
        $output = new Output();
        $output->line('plain text');

        $this->assertSame(['plain text'], $output->getBuffer());
    }

    public function test_table_rendering(): void
    {
        $output = new Output();
        $output->table(['Name', 'Age'], [
            ['Alice', '30'],
            ['Bob', '25'],
        ]);

        $buffer = $output->getBuffer();

        // Should have: separator, header, separator, row1, row2, separator
        $this->assertCount(6, $buffer);
        $this->assertStringContainsString('Alice', $buffer[3]);
        $this->assertStringContainsString('Bob', $buffer[4]);
        $this->assertStringContainsString('Name', $buffer[1]);
    }

    public function test_flush_returns_and_clears_buffer(): void
    {
        $output = new Output();
        $output->info('First');
        $output->info('Second');

        $flushed = $output->flush();

        $this->assertStringContainsString('First', $flushed);
        $this->assertStringContainsString('Second', $flushed);
        $this->assertSame([], $output->getBuffer());
    }

    public function test_multiple_messages_accumulate(): void
    {
        $output = new Output();
        $output->info('one');
        $output->error('two');
        $output->success('three');

        $this->assertCount(3, $output->getBuffer());
    }
}
