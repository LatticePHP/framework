<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Storage;

use Lattice\Nightwatch\Storage\NdjsonWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NdjsonWriterTest extends TestCase
{
    private string $tempDir;
    private NdjsonWriter $writer;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->writer = new NdjsonWriter();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_single_write(): void
    {
        $filePath = $this->tempDir . '/test.ndjson';
        $entry = ['type' => 'request', 'method' => 'GET', 'uri' => '/api/users'];

        $this->writer->write($filePath, $entry);

        $this->assertFileExists($filePath);
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true);
        $this->assertSame('request', $decoded['type']);
        $this->assertSame('GET', $decoded['method']);
        $this->assertSame('/api/users', $decoded['uri']);
    }

    #[Test]
    public function test_batch_write(): void
    {
        $filePath = $this->tempDir . '/batch.ndjson';
        $entries = [
            ['type' => 'request', 'method' => 'GET'],
            ['type' => 'request', 'method' => 'POST'],
            ['type' => 'request', 'method' => 'DELETE'],
        ];

        $this->writer->writeBatch($filePath, $entries);

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(3, $lines);

        $this->assertSame('GET', json_decode($lines[0], true)['method']);
        $this->assertSame('POST', json_decode($lines[1], true)['method']);
        $this->assertSame('DELETE', json_decode($lines[2], true)['method']);
    }

    #[Test]
    public function test_batch_write_empty_array(): void
    {
        $filePath = $this->tempDir . '/empty.ndjson';

        $this->writer->writeBatch($filePath, []);

        $this->assertFileDoesNotExist($filePath);
    }

    #[Test]
    public function test_append_to_existing_file(): void
    {
        $filePath = $this->tempDir . '/append.ndjson';

        $this->writer->write($filePath, ['entry' => 1]);
        $this->writer->write($filePath, ['entry' => 2]);

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(2, $lines);
        $this->assertSame(1, json_decode($lines[0], true)['entry']);
        $this->assertSame(2, json_decode($lines[1], true)['entry']);
    }

    #[Test]
    public function test_creates_nested_directories(): void
    {
        $filePath = $this->tempDir . '/deep/nested/dir/events.ndjson';

        $this->writer->write($filePath, ['test' => true]);

        $this->assertFileExists($filePath);
    }

    #[Test]
    public function test_unicode_content(): void
    {
        $filePath = $this->tempDir . '/unicode.ndjson';
        $entry = ['message' => 'Hello, world!', 'name' => "\u{1F600}"];

        $this->writer->write($filePath, $entry);

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $decoded = json_decode($lines[0], true);
        $this->assertSame("\u{1F600}", $decoded['name']);
    }

    #[Test]
    public function test_each_line_is_valid_json(): void
    {
        $filePath = $this->tempDir . '/valid.ndjson';

        for ($i = 0; $i < 10; $i++) {
            $this->writer->write($filePath, ['index' => $i, 'data' => str_repeat('x', 100)]);
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(10, $lines);

        foreach ($lines as $index => $line) {
            $decoded = json_decode($line, true);
            $this->assertNotNull($decoded, "Line $index is not valid JSON: $line");
            $this->assertSame($index, $decoded['index']);
        }
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
