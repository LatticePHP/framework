<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Logger\InMemoryLogHandler;
use Lattice\Observability\Logger\LogEntry;
use Lattice\Observability\Logger\LogHandlerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryLogHandlerTest extends TestCase
{
    #[Test]
    public function it_implements_log_handler_interface(): void
    {
        $handler = new InMemoryLogHandler();
        $this->assertInstanceOf(LogHandlerInterface::class, $handler);
    }

    #[Test]
    public function it_starts_with_empty_entries(): void
    {
        $handler = new InMemoryLogHandler();
        $this->assertSame([], $handler->getEntries());
    }

    #[Test]
    public function it_stores_log_entries(): void
    {
        $handler = new InMemoryLogHandler();

        $entry1 = new LogEntry('info', 'First message', [], microtime(true));
        $entry2 = new LogEntry('error', 'Second message', ['key' => 'val'], microtime(true));

        $handler->handle($entry1);
        $handler->handle($entry2);

        $entries = $handler->getEntries();
        $this->assertCount(2, $entries);
        $this->assertSame($entry1, $entries[0]);
        $this->assertSame($entry2, $entries[1]);
    }

    #[Test]
    public function has_entry_returns_true_for_matching_level_and_pattern(): void
    {
        $handler = new InMemoryLogHandler();
        $handler->handle(new LogEntry('info', 'User logged in', [], microtime(true)));
        $handler->handle(new LogEntry('error', 'Database connection failed', [], microtime(true)));

        $this->assertTrue($handler->hasEntry('info', '/User/'));
        $this->assertTrue($handler->hasEntry('error', '/Database.*failed/'));
    }

    #[Test]
    public function has_entry_returns_false_for_non_matching_level(): void
    {
        $handler = new InMemoryLogHandler();
        $handler->handle(new LogEntry('info', 'User logged in', [], microtime(true)));

        $this->assertFalse($handler->hasEntry('error', '/User/'));
    }

    #[Test]
    public function has_entry_returns_false_for_non_matching_pattern(): void
    {
        $handler = new InMemoryLogHandler();
        $handler->handle(new LogEntry('info', 'User logged in', [], microtime(true)));

        $this->assertFalse($handler->hasEntry('info', '/Database/'));
    }

    #[Test]
    public function has_entry_returns_false_on_empty_entries(): void
    {
        $handler = new InMemoryLogHandler();
        $this->assertFalse($handler->hasEntry('info', '/anything/'));
    }
}
