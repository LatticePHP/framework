<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Storage;

use Lattice\Nightwatch\Storage\NdjsonReader;
use Lattice\Nightwatch\Storage\NdjsonWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NdjsonReaderTest extends TestCase
{
    private string $tempDir;
    private NdjsonReader $reader;
    private NdjsonWriter $writer;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_reader_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->reader = new NdjsonReader();
        $this->writer = new NdjsonWriter();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_read_single_file(): void
    {
        $filePath = $this->tempDir . '/test.ndjson';
        $this->writer->writeBatch($filePath, [
            ['type' => 'request', 'index' => 0],
            ['type' => 'request', 'index' => 1],
            ['type' => 'request', 'index' => 2],
        ]);

        $entries = iterator_to_array($this->reader->read($filePath));

        $this->assertCount(3, $entries);
        $this->assertSame(0, $entries[0]['index']);
        $this->assertSame(1, $entries[1]['index']);
        $this->assertSame(2, $entries[2]['index']);
    }

    #[Test]
    public function test_read_nonexistent_file(): void
    {
        $entries = iterator_to_array($this->reader->read($this->tempDir . '/nonexistent.ndjson'));

        $this->assertCount(0, $entries);
    }

    #[Test]
    public function test_read_with_filter(): void
    {
        $filePath = $this->tempDir . '/filter.ndjson';
        $this->writer->writeBatch($filePath, [
            ['type' => 'request', 'status' => 200],
            ['type' => 'request', 'status' => 404],
            ['type' => 'request', 'status' => 500],
            ['type' => 'request', 'status' => 200],
        ]);

        $entries = iterator_to_array($this->reader->read(
            $filePath,
            fn(array $e) => $e['status'] >= 400,
        ));

        $this->assertCount(2, $entries);
        $this->assertSame(404, array_values($entries)[0]['status']);
        $this->assertSame(500, array_values($entries)[1]['status']);
    }

    #[Test]
    public function test_read_paginated(): void
    {
        $filePath = $this->tempDir . '/paginate.ndjson';
        for ($i = 0; $i < 20; $i++) {
            $this->writer->write($filePath, ['index' => $i]);
        }

        $page1 = $this->reader->readPaginated($filePath, offset: 0, limit: 5);
        $page2 = $this->reader->readPaginated($filePath, offset: 5, limit: 5);

        $this->assertCount(5, $page1);
        $this->assertSame(0, $page1[0]['index']);
        $this->assertSame(4, $page1[4]['index']);

        $this->assertCount(5, $page2);
        $this->assertSame(5, $page2[0]['index']);
        $this->assertSame(9, $page2[4]['index']);
    }

    #[Test]
    public function test_read_paginated_with_filter(): void
    {
        $filePath = $this->tempDir . '/paginate_filter.ndjson';
        for ($i = 0; $i < 20; $i++) {
            $this->writer->write($filePath, ['index' => $i, 'even' => $i % 2 === 0]);
        }

        $evens = $this->reader->readPaginated(
            $filePath,
            offset: 0,
            limit: 5,
            filter: fn(array $e) => $e['even'] === true,
        );

        $this->assertCount(5, $evens);
        $this->assertSame(0, $evens[0]['index']);
        $this->assertSame(8, $evens[4]['index']);
    }

    #[Test]
    public function test_read_reverse(): void
    {
        $filePath = $this->tempDir . '/reverse.ndjson';
        for ($i = 0; $i < 10; $i++) {
            $this->writer->write($filePath, ['index' => $i]);
        }

        $entries = $this->reader->readReverse($filePath, limit: 3);

        $this->assertCount(3, $entries);
        $this->assertSame(9, $entries[0]['index']);
        $this->assertSame(8, $entries[1]['index']);
        $this->assertSame(7, $entries[2]['index']);
    }

    #[Test]
    public function test_read_reverse_nonexistent_file(): void
    {
        $entries = $this->reader->readReverse($this->tempDir . '/nonexistent.ndjson');

        $this->assertCount(0, $entries);
    }

    #[Test]
    public function test_corrupted_lines_are_skipped(): void
    {
        $filePath = $this->tempDir . '/corrupted.ndjson';
        file_put_contents($filePath, implode("\n", [
            '{"valid":1}',
            'this is not json',
            '{"valid":2}',
            '{invalid json too',
            '{"valid":3}',
        ]) . "\n");

        $entries = iterator_to_array($this->reader->read($filePath));

        $this->assertCount(3, $entries);
        $this->assertSame(1, array_values($entries)[0]['valid']);
        $this->assertSame(2, array_values($entries)[1]['valid']);
        $this->assertSame(3, array_values($entries)[2]['valid']);
    }

    #[Test]
    public function test_read_multiple_files(): void
    {
        $file1 = $this->tempDir . '/file1.ndjson';
        $file2 = $this->tempDir . '/file2.ndjson';
        $file3 = $this->tempDir . '/nonexistent.ndjson';

        $this->writer->writeBatch($file1, [['source' => 'a'], ['source' => 'b']]);
        $this->writer->writeBatch($file2, [['source' => 'c'], ['source' => 'd']]);

        $entries = iterator_to_array($this->reader->readMultiple([$file1, $file2, $file3]), false);

        $this->assertCount(4, $entries);
        $this->assertSame('a', $entries[0]['source']);
        $this->assertSame('d', $entries[3]['source']);
    }

    #[Test]
    public function test_count_entries(): void
    {
        $filePath = $this->tempDir . '/count.ndjson';
        for ($i = 0; $i < 15; $i++) {
            $this->writer->write($filePath, ['index' => $i, 'even' => $i % 2 === 0]);
        }

        $totalCount = $this->reader->count($filePath);
        $this->assertSame(15, $totalCount);

        $evenCount = $this->reader->count(
            $filePath,
            fn(array $e) => $e['even'] === true,
        );
        $this->assertSame(8, $evenCount);
    }

    #[Test]
    public function test_empty_lines_are_skipped(): void
    {
        $filePath = $this->tempDir . '/empty_lines.ndjson';
        file_put_contents($filePath, "  \n\n{\"valid\":1}\n\n{\"valid\":2}\n  \n");

        $entries = iterator_to_array($this->reader->read($filePath));

        $this->assertCount(2, $entries);
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
