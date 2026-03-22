<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Tests;

use Lattice\Filesystem\Driver\InMemoryFilesystem;
use Lattice\Filesystem\FilesystemInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryFilesystemTest extends TestCase
{
    private InMemoryFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new InMemoryFilesystem();
    }

    #[Test]
    public function it_implements_filesystem_interface(): void
    {
        $this->assertInstanceOf(FilesystemInterface::class, $this->fs);
    }

    #[Test]
    public function it_writes_and_reads_a_file(): void
    {
        $this->fs->write('hello.txt', 'Hello!');
        $this->assertSame('Hello!', $this->fs->read('hello.txt'));
    }

    #[Test]
    public function it_checks_file_exists(): void
    {
        $this->assertFalse($this->fs->exists('nope.txt'));
        $this->fs->write('nope.txt', 'content');
        $this->assertTrue($this->fs->exists('nope.txt'));
    }

    #[Test]
    public function it_deletes_a_file(): void
    {
        $this->fs->write('del.txt', 'data');
        $this->assertTrue($this->fs->delete('del.txt'));
        $this->assertFalse($this->fs->exists('del.txt'));
    }

    #[Test]
    public function it_copies_a_file(): void
    {
        $this->fs->write('original.txt', 'content');
        $this->fs->copy('original.txt', 'copy.txt');

        $this->assertTrue($this->fs->exists('copy.txt'));
        $this->assertSame('content', $this->fs->read('copy.txt'));
    }

    #[Test]
    public function it_moves_a_file(): void
    {
        $this->fs->write('src.txt', 'data');
        $this->fs->move('src.txt', 'dst.txt');

        $this->assertFalse($this->fs->exists('src.txt'));
        $this->assertTrue($this->fs->exists('dst.txt'));
        $this->assertSame('data', $this->fs->read('dst.txt'));
    }

    #[Test]
    public function it_lists_directory_contents(): void
    {
        $this->fs->write('dir/a.txt', 'a');
        $this->fs->write('dir/b.txt', 'b');
        $this->fs->write('other.txt', 'c');

        $contents = $this->fs->listContents('dir');
        sort($contents);

        $this->assertSame(['a.txt', 'b.txt'], $contents);
    }

    #[Test]
    public function it_creates_and_deletes_directory(): void
    {
        $this->fs->createDirectory('subdir');
        $this->assertTrue($this->fs->exists('subdir'));

        $this->fs->write('subdir/file.txt', 'nested');
        $this->assertTrue($this->fs->deleteDirectory('subdir'));
        $this->assertFalse($this->fs->exists('subdir'));
        $this->assertFalse($this->fs->exists('subdir/file.txt'));
    }

    #[Test]
    public function it_returns_last_modified_time(): void
    {
        $this->fs->write('mod.txt', 'data');
        $time = $this->fs->lastModified('mod.txt');

        $this->assertIsInt($time);
        $this->assertGreaterThan(0, $time);
    }

    #[Test]
    public function it_returns_file_size(): void
    {
        $this->fs->write('size.txt', 'hello');
        $this->assertSame(5, $this->fs->fileSize('size.txt'));
    }

    #[Test]
    public function it_returns_mime_type(): void
    {
        $this->fs->write('test.json', '{}');
        $mime = $this->fs->mimeType('test.json');
        $this->assertSame('application/json', $mime);
    }

    #[Test]
    public function it_returns_url(): void
    {
        $this->assertSame('path/to/file.txt', $this->fs->url('path/to/file.txt'));
    }

    #[Test]
    public function it_throws_for_temporary_url(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->fs->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function it_throws_when_reading_nonexistent_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->fs->read('nonexistent.txt');
    }

    #[Test]
    public function it_lists_root_contents(): void
    {
        $this->fs->write('a.txt', 'a');
        $this->fs->write('b.txt', 'b');
        $this->fs->write('sub/c.txt', 'c');

        $contents = $this->fs->listContents('');
        sort($contents);

        $this->assertSame(['a.txt', 'b.txt', 'sub'], $contents);
    }
}
