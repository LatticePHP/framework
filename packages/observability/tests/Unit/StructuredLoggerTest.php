<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Logger\InMemoryLogHandler;
use Lattice\Observability\Logger\StructuredLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class StructuredLoggerTest extends TestCase
{
    #[Test]
    public function it_implements_psr3_logger_interface(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    #[Test]
    #[DataProvider('logLevelProvider')]
    public function it_logs_at_all_psr3_levels(string $level): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler);

        $logger->{$level}("Test message at {$level}");

        $entries = $handler->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($level, $entries[0]->level);
        $this->assertSame("Test message at {$level}", $entries[0]->message);
    }

    public static function logLevelProvider(): array
    {
        return [
            'emergency' => [LogLevel::EMERGENCY],
            'alert' => [LogLevel::ALERT],
            'critical' => [LogLevel::CRITICAL],
            'error' => [LogLevel::ERROR],
            'warning' => [LogLevel::WARNING],
            'notice' => [LogLevel::NOTICE],
            'info' => [LogLevel::INFO],
            'debug' => [LogLevel::DEBUG],
        ];
    }

    #[Test]
    public function it_includes_context_in_log_entry(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler);

        $logger->info('User logged in', ['user_id' => 42, 'ip' => '127.0.0.1']);

        $entry = $handler->getEntries()[0];
        $this->assertSame(['user_id' => 42, 'ip' => '127.0.0.1'], $entry->context);
    }

    #[Test]
    public function it_includes_timestamp_in_log_entry(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler);

        $before = microtime(true);
        $logger->info('Test');
        $after = microtime(true);

        $entry = $handler->getEntries()[0];
        $this->assertGreaterThanOrEqual($before, $entry->timestamp);
        $this->assertLessThanOrEqual($after, $entry->timestamp);
    }

    #[Test]
    public function it_includes_correlation_id_when_provided(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler, correlationId: 'corr-xyz');

        $logger->info('Test');

        $entry = $handler->getEntries()[0];
        $this->assertSame('corr-xyz', $entry->correlationId);
    }

    #[Test]
    public function it_has_null_correlation_id_when_not_provided(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler);

        $logger->info('Test');

        $entry = $handler->getEntries()[0];
        $this->assertNull($entry->correlationId);
    }

    #[Test]
    public function log_entry_produces_valid_json(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler, correlationId: 'corr-json');

        $logger->error('Something failed', ['reason' => 'timeout']);

        $entry = $handler->getEntries()[0];
        $json = json_encode($entry->toArray());

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame('error', $decoded['level']);
        $this->assertSame('Something failed', $decoded['message']);
        $this->assertSame('corr-json', $decoded['correlationId']);
    }

    #[Test]
    public function it_accepts_stringable_messages(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new StructuredLogger($handler);

        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };

        $logger->info($stringable);

        $entry = $handler->getEntries()[0];
        $this->assertSame('Stringable message', $entry->message);
    }
}
