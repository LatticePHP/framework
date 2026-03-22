<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Watchers;

use InvalidArgumentException;
use Lattice\Nightwatch\Storage\StorageManager;
use Lattice\Nightwatch\Watchers\ContextProviderInterface;
use Lattice\Nightwatch\Watchers\ExceptionWatcher;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionWatcherTest extends TestCase
{
    private string $tempDir;
    private StorageManager $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_ew_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new StorageManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_capture_basic_exception(): void
    {
        $watcher = new ExceptionWatcher($this->storage);
        $exception = new RuntimeException('Something went wrong', 42);

        $entry = $watcher->capture($exception);

        $this->assertNotNull($entry);
        $this->assertSame('exception', $entry->type->value);
        $this->assertSame(RuntimeException::class, $entry->data['class']);
        $this->assertSame('Something went wrong', $entry->data['message']);
        $this->assertSame(42, $entry->data['code']);
        $this->assertIsArray($entry->data['trace']);
        $this->assertNotEmpty($entry->data['trace']);
    }

    #[Test]
    public function test_capture_nested_exception(): void
    {
        $watcher = new ExceptionWatcher($this->storage);
        $previous = new LogicException('Root cause');
        $exception = new RuntimeException('Wrapper', 0, $previous);

        $entry = $watcher->capture($exception);

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('previous', $entry->data);
        $this->assertSame(LogicException::class, $entry->data['previous']['class']);
        $this->assertSame('Root cause', $entry->data['previous']['message']);
    }

    #[Test]
    public function test_capture_with_request_context(): void
    {
        $watcher = new ExceptionWatcher($this->storage);
        $exception = new RuntimeException('Error');
        $context = ['method' => 'POST', 'uri' => '/api/users', 'ip' => '10.0.0.1'];

        $entry = $watcher->capture($exception, $context, 'batch-abc');

        $this->assertNotNull($entry);
        $this->assertSame($context, $entry->data['request_context']);
        $this->assertSame('batch-abc', $entry->batchId);
    }

    #[Test]
    public function test_ignored_exception_returns_null(): void
    {
        $watcher = new ExceptionWatcher(
            $this->storage,
            ignoredExceptions: [InvalidArgumentException::class],
        );

        $entry = $watcher->capture(new InvalidArgumentException('Ignored'));

        $this->assertNull($entry);
    }

    #[Test]
    public function test_ignored_exception_subclass(): void
    {
        $watcher = new ExceptionWatcher(
            $this->storage,
            ignoredExceptions: [LogicException::class],
        );

        // InvalidArgumentException extends LogicException
        $entry = $watcher->capture(new InvalidArgumentException('Also ignored'));

        $this->assertNull($entry);
    }

    #[Test]
    public function test_non_ignored_exception_is_captured(): void
    {
        $watcher = new ExceptionWatcher(
            $this->storage,
            ignoredExceptions: [InvalidArgumentException::class],
        );

        $entry = $watcher->capture(new RuntimeException('Not ignored'));

        $this->assertNotNull($entry);
    }

    #[Test]
    public function test_exception_tags(): void
    {
        $watcher = new ExceptionWatcher($this->storage);
        $entry = $watcher->capture(new RuntimeException('Test'));

        $this->assertNotNull($entry);
        $this->assertContains('exception:RuntimeException', $entry->tags);
    }

    #[Test]
    public function test_trace_format(): void
    {
        $watcher = new ExceptionWatcher($this->storage);
        $entry = $watcher->capture(new RuntimeException('Test'));

        $this->assertNotNull($entry);
        $trace = $entry->data['trace'];
        $this->assertNotEmpty($trace);

        $frame = $trace[0];
        $this->assertArrayHasKey('file', $frame);
        $this->assertArrayHasKey('line', $frame);
        $this->assertArrayHasKey('class', $frame);
        $this->assertArrayHasKey('function', $frame);
        $this->assertArrayHasKey('type', $frame);
    }

    #[Test]
    public function test_context_provider_interface(): void
    {
        $watcher = new ExceptionWatcher($this->storage);

        $exception = new class('Custom context') extends RuntimeException implements ContextProviderInterface {
            public function context(): array
            {
                return ['custom_key' => 'custom_value', 'user_id' => 42];
            }
        };

        $entry = $watcher->capture($exception);

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('custom_context', $entry->data);
        $this->assertSame('custom_value', $entry->data['custom_context']['custom_key']);
        $this->assertSame(42, $entry->data['custom_context']['user_id']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
