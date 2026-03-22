<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use Lattice\Observability\Logger\DailyFileHandler;
use Lattice\Observability\Logger\LogEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DailyFileHandlerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lattice_daily_handler_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function test_writes_to_dated_file(): void
    {
        $basePath = $this->tempDir . '/lattice';
        $handler = new DailyFileHandler($basePath);

        $entry = new LogEntry(
            level: 'info',
            message: 'Test log entry',
            context: ['user' => 'test'],
            timestamp: microtime(true),
        );

        $handler->handle($entry);

        $date = date('Y-m-d');
        $expectedFile = "{$basePath}-{$date}.log";

        $this->assertFileExists($expectedFile);

        $content = file_get_contents($expectedFile);
        $this->assertIsString($content);
        $this->assertNotEmpty($content);

        $decoded = json_decode(trim($content), true);
        $this->assertIsArray($decoded);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('Test log entry', $decoded['message']);
    }

    #[Test]
    public function test_appends_multiple_entries(): void
    {
        $basePath = $this->tempDir . '/lattice';
        $handler = new DailyFileHandler($basePath);

        $handler->handle(new LogEntry(
            level: 'info',
            message: 'First entry',
            context: [],
            timestamp: microtime(true),
        ));

        $handler->handle(new LogEntry(
            level: 'error',
            message: 'Second entry',
            context: [],
            timestamp: microtime(true),
        ));

        $date = date('Y-m-d');
        $file = "{$basePath}-{$date}.log";

        $lines = array_filter(explode("\n", file_get_contents($file)));
        $this->assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        $second = json_decode($lines[1], true);

        $this->assertSame('First entry', $first['message']);
        $this->assertSame('Second entry', $second['message']);
    }

    #[Test]
    public function test_creates_directory_if_not_exists(): void
    {
        $nestedDir = $this->tempDir . '/nested/deep';
        $basePath = $nestedDir . '/lattice';
        $handler = new DailyFileHandler($basePath);

        $handler->handle(new LogEntry(
            level: 'info',
            message: 'Nested directory test',
            context: [],
            timestamp: microtime(true),
        ));

        $date = date('Y-m-d');
        $expectedFile = "{$basePath}-{$date}.log";

        $this->assertFileExists($expectedFile);

        // Clean up nested dirs
        $files = glob($nestedDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($nestedDir);
        rmdir($this->tempDir . '/nested');
    }

    #[Test]
    public function test_retention_days_configuration(): void
    {
        $handler = new DailyFileHandler($this->tempDir . '/app', retentionDays: 7);
        $this->assertSame(7, $handler->getRetentionDays());
    }

    #[Test]
    public function test_base_path_accessor(): void
    {
        $basePath = $this->tempDir . '/app';
        $handler = new DailyFileHandler($basePath);
        $this->assertSame($basePath, $handler->getBasePath());
    }

    #[Test]
    public function test_json_output_is_valid(): void
    {
        $basePath = $this->tempDir . '/lattice';
        $handler = new DailyFileHandler($basePath);

        $handler->handle(new LogEntry(
            level: 'warning',
            message: 'Unicode test: cafe\u0301',
            context: ['emoji' => 'test', 'path' => '/api/v1/users'],
            timestamp: 1700000000.123456,
            correlationId: 'corr-abc-123',
        ));

        $date = date('Y-m-d');
        $file = "{$basePath}-{$date}.log";
        $content = trim(file_get_contents($file));

        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Output should be valid JSON');
        $this->assertSame('warning', $decoded['level']);
        $this->assertSame('corr-abc-123', $decoded['correlationId']);
    }
}
