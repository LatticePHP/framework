<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use Lattice\Observability\Log;
use Lattice\Observability\Logger\InMemoryLogHandler;
use Lattice\Observability\Logger\StructuredLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        Log::reset();
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    #[Test]
    public function test_info_writes_to_handler(): void
    {
        $handler = new InMemoryLogHandler();
        Log::setInstance(new StructuredLogger($handler));

        Log::info('Test info message', ['key' => 'value']);

        $entries = $handler->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(LogLevel::INFO, $entries[0]->level);
        $this->assertSame('Test info message', $entries[0]->message);
        $this->assertSame(['key' => 'value'], $entries[0]->context);
    }

    #[Test]
    public function test_error_writes_to_handler(): void
    {
        $handler = new InMemoryLogHandler();
        Log::setInstance(new StructuredLogger($handler));

        Log::error('Something broke', ['code' => 500]);

        $entries = $handler->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(LogLevel::ERROR, $entries[0]->level);
        $this->assertSame('Something broke', $entries[0]->message);
        $this->assertSame(['code' => 500], $entries[0]->context);
    }

    #[Test]
    public function test_warning_writes_to_handler(): void
    {
        $handler = new InMemoryLogHandler();
        Log::setInstance(new StructuredLogger($handler));

        Log::warning('Low disk space', ['disk' => '/dev/sda1']);

        $entries = $handler->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(LogLevel::WARNING, $entries[0]->level);
        $this->assertSame('Low disk space', $entries[0]->message);
    }

    #[Test]
    public function test_all_log_levels_write_to_handler(): void
    {
        $handler = new InMemoryLogHandler();
        Log::setInstance(new StructuredLogger($handler));

        Log::emergency('emergency msg');
        Log::alert('alert msg');
        Log::critical('critical msg');
        Log::error('error msg');
        Log::warning('warning msg');
        Log::notice('notice msg');
        Log::info('info msg');
        Log::debug('debug msg');

        $entries = $handler->getEntries();
        $this->assertCount(8, $entries);
        $this->assertSame(LogLevel::EMERGENCY, $entries[0]->level);
        $this->assertSame(LogLevel::ALERT, $entries[1]->level);
        $this->assertSame(LogLevel::CRITICAL, $entries[2]->level);
        $this->assertSame(LogLevel::ERROR, $entries[3]->level);
        $this->assertSame(LogLevel::WARNING, $entries[4]->level);
        $this->assertSame(LogLevel::NOTICE, $entries[5]->level);
        $this->assertSame(LogLevel::INFO, $entries[6]->level);
        $this->assertSame(LogLevel::DEBUG, $entries[7]->level);
    }

    #[Test]
    public function test_get_instance_creates_default_logger(): void
    {
        // Without setInstance, getInstance should return a default StructuredLogger
        $logger = Log::getInstance();
        $this->assertInstanceOf(StructuredLogger::class, $logger);
    }

    #[Test]
    public function test_set_instance_overrides_default(): void
    {
        $handler = new InMemoryLogHandler();
        $custom = new StructuredLogger($handler);
        Log::setInstance($custom);

        $this->assertSame($custom, Log::getInstance());
    }

    #[Test]
    public function test_reset_clears_instance(): void
    {
        $handler = new InMemoryLogHandler();
        $custom = new StructuredLogger($handler);
        Log::setInstance($custom);

        Log::reset();

        // After reset, a new default logger should be created
        $newInstance = Log::getInstance();
        $this->assertNotSame($custom, $newInstance);
    }
}
