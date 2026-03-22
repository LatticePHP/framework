<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use Lattice\Observability\Logger\InMemoryLogHandler;
use Lattice\Observability\Logger\LogEntry;
use Lattice\Observability\Logger\MultiChannelHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MultiChannelHandlerTest extends TestCase
{
    #[Test]
    public function test_routes_to_multiple_handlers(): void
    {
        $handler1 = new InMemoryLogHandler();
        $handler2 = new InMemoryLogHandler();

        $multi = new MultiChannelHandler();
        $multi->addHandler('console', $handler1);
        $multi->addHandler('file', $handler2);

        $entry = new LogEntry(
            level: 'info',
            message: 'Multi-channel test',
            context: ['key' => 'value'],
            timestamp: microtime(true),
        );

        $multi->handle($entry);

        $this->assertCount(1, $handler1->getEntries());
        $this->assertCount(1, $handler2->getEntries());
        $this->assertSame('Multi-channel test', $handler1->getEntries()[0]->message);
        $this->assertSame('Multi-channel test', $handler2->getEntries()[0]->message);
    }

    #[Test]
    public function test_add_and_remove_handlers(): void
    {
        $handler1 = new InMemoryLogHandler();
        $handler2 = new InMemoryLogHandler();

        $multi = new MultiChannelHandler();
        $multi->addHandler('console', $handler1);
        $multi->addHandler('file', $handler2);

        $this->assertTrue($multi->hasHandler('console'));
        $this->assertTrue($multi->hasHandler('file'));
        $this->assertFalse($multi->hasHandler('missing'));

        $multi->removeHandler('console');
        $this->assertFalse($multi->hasHandler('console'));

        $entry = new LogEntry(
            level: 'info',
            message: 'After removal',
            context: [],
            timestamp: microtime(true),
        );

        $multi->handle($entry);

        // Only file handler should receive the entry
        $this->assertCount(0, $handler1->getEntries());
        $this->assertCount(1, $handler2->getEntries());
    }

    #[Test]
    public function test_handles_no_handlers_gracefully(): void
    {
        $multi = new MultiChannelHandler();

        $entry = new LogEntry(
            level: 'info',
            message: 'No handlers',
            context: [],
            timestamp: microtime(true),
        );

        // Should not throw
        $multi->handle($entry);
        $this->assertEmpty($multi->getHandlers());
    }

    #[Test]
    public function test_get_handlers_returns_all_registered(): void
    {
        $handler1 = new InMemoryLogHandler();
        $handler2 = new InMemoryLogHandler();

        $multi = new MultiChannelHandler();
        $multi->addHandler('a', $handler1);
        $multi->addHandler('b', $handler2);

        $handlers = $multi->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertArrayHasKey('a', $handlers);
        $this->assertArrayHasKey('b', $handlers);
    }

    #[Test]
    public function test_same_channel_replaces_handler(): void
    {
        $handler1 = new InMemoryLogHandler();
        $handler2 = new InMemoryLogHandler();

        $multi = new MultiChannelHandler();
        $multi->addHandler('console', $handler1);
        $multi->addHandler('console', $handler2);

        $entry = new LogEntry(
            level: 'info',
            message: 'Replaced handler',
            context: [],
            timestamp: microtime(true),
        );

        $multi->handle($entry);

        // handler1 was replaced, so only handler2 should receive
        $this->assertCount(0, $handler1->getEntries());
        $this->assertCount(1, $handler2->getEntries());
    }
}
