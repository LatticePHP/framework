<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Storage;

use DateTimeImmutable;
use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\StackFrame;
use Lattice\Prism\Storage\LocalFilesystemStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocalFilesystemStorageTest extends TestCase
{
    private string $tempDir;
    private LocalFilesystemStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/prism_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new LocalFilesystemStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_store_creates_ndjson_file(): void
    {
        $event = $this->makeEvent(new DateTimeImmutable('2026-03-22 14:30:00'));

        $result = $this->storage->store($event);

        $this->assertArrayHasKey('blob_path', $result);
        $this->assertArrayHasKey('byte_offset', $result);
        $this->assertSame(0, $result['byte_offset']);

        // Verify file was created
        $fullPath = $this->tempDir . '/' . $result['blob_path'];
        $this->assertFileExists($fullPath);
    }

    #[Test]
    public function test_store_appends_to_existing_file(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');
        $event1 = $this->makeEvent($timestamp);
        $event2 = $this->makeEvent($timestamp, 'event-2');

        $result1 = $this->storage->store($event1);
        $result2 = $this->storage->store($event2);

        $this->assertSame(0, $result1['byte_offset']);
        $this->assertGreaterThan(0, $result2['byte_offset']);
        $this->assertSame($result1['blob_path'], $result2['blob_path']);

        // Verify both lines are in the file
        $fullPath = $this->tempDir . '/' . $result1['blob_path'];
        $lines = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(2, $lines);
    }

    #[Test]
    public function test_store_and_retrieve_round_trip(): void
    {
        $event = $this->makeEvent(new DateTimeImmutable('2026-03-22 14:30:00'));

        $result = $this->storage->store($event);
        $entries = $this->storage->retrieve($result['blob_path']);

        $this->assertCount(1, $entries);
        $this->assertSame('proj-1', $entries[0]['project_id']);
        $this->assertSame('production', $entries[0]['environment']);
        $this->assertSame('error', $entries[0]['level']);
        $this->assertSame('RuntimeException', $entries[0]['exception']['type']);
    }

    #[Test]
    public function test_retrieve_with_offset(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');

        $result1 = $this->storage->store($this->makeEvent($timestamp, 'event-1'));
        $result2 = $this->storage->store($this->makeEvent($timestamp, 'event-2'));
        $this->storage->store($this->makeEvent($timestamp, 'event-3'));

        // Retrieve from second event's offset
        $entries = $this->storage->retrieve($result2['blob_path'], $result2['byte_offset']);

        $this->assertCount(2, $entries);
        $this->assertSame('event-2', $entries[0]['event_id']);
        $this->assertSame('event-3', $entries[1]['event_id']);
    }

    #[Test]
    public function test_retrieve_with_limit(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');

        for ($i = 0; $i < 10; $i++) {
            $this->storage->store($this->makeEvent($timestamp, "event-$i"));
        }

        $result = $this->storage->store($this->makeEvent($timestamp, 'event-10'));
        $entries = $this->storage->retrieve($result['blob_path'], 0, 3);

        $this->assertCount(3, $entries);
    }

    #[Test]
    public function test_retrieve_nonexistent_file(): void
    {
        $entries = $this->storage->retrieve('nonexistent/path/events.ndjson');

        $this->assertSame([], $entries);
    }

    #[Test]
    public function test_path_layout(): void
    {
        $path = $this->storage->buildPath('proj-1', 'production', new DateTimeImmutable('2026-03-22 14:00:00'));

        $this->assertSame('2026/03/22/14/proj-1/production/events.ndjson', $path);
    }

    #[Test]
    public function test_different_hours_different_files(): void
    {
        $event1 = $this->makeEvent(new DateTimeImmutable('2026-03-22 14:30:00'), 'event-hour-14');
        $event2 = $this->makeEvent(new DateTimeImmutable('2026-03-22 15:30:00'), 'event-hour-15');

        $result1 = $this->storage->store($event1);
        $result2 = $this->storage->store($event2);

        $this->assertNotSame($result1['blob_path'], $result2['blob_path']);
    }

    #[Test]
    public function test_different_projects_different_files(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');

        $event1 = new ErrorEvent(
            eventId: 'event-p1',
            timestamp: $timestamp,
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'Exception',
            exceptionMessage: 'Error',
        );

        $event2 = new ErrorEvent(
            eventId: 'event-p2',
            timestamp: $timestamp,
            projectId: 'proj-2',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'Exception',
            exceptionMessage: 'Error',
        );

        $result1 = $this->storage->store($event1);
        $result2 = $this->storage->store($event2);

        $this->assertNotSame($result1['blob_path'], $result2['blob_path']);
        $this->assertStringContainsString('proj-1', $result1['blob_path']);
        $this->assertStringContainsString('proj-2', $result2['blob_path']);
    }

    #[Test]
    public function test_auto_creates_nested_directories(): void
    {
        $event = $this->makeEvent(new DateTimeImmutable('2026-06-15 10:00:00'));

        $this->storage->store($event);

        $expectedDir = $this->tempDir . '/2026/06/15/10/proj-1/production';
        $this->assertDirectoryExists($expectedDir);
    }

    private function makeEvent(DateTimeImmutable $timestamp, string $eventId = '550e8400-e29b-41d4-a716-446655440000'): ErrorEvent
    {
        return new ErrorEvent(
            eventId: $eventId,
            timestamp: $timestamp,
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'RuntimeException',
            exceptionMessage: 'Something went wrong',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
            ],
        );
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
