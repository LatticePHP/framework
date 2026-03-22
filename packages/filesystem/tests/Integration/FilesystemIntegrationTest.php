<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Tests\Integration;

use Lattice\Filesystem\Driver\InMemoryFilesystem;
use Lattice\Filesystem\Driver\LocalFilesystem;
use Lattice\Filesystem\Facades\Storage;
use Lattice\Filesystem\FilesystemInterface;
use Lattice\Filesystem\FilesystemManager;
use PHPUnit\Framework\TestCase;

final class FilesystemIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lattice_fs_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        Storage::reset();
    }

    protected function tearDown(): void
    {
        Storage::reset();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    // ─── 1. LocalFilesystem put→get ─────────────────────────────────────

    public function test_local_filesystem_put_get_returns_same_content(): void
    {
        $fs = new LocalFilesystem($this->tempDir);

        $fs->write('hello.txt', 'Hello, World!');

        $this->assertSame('Hello, World!', $fs->read('hello.txt'));
    }

    // ─── 2. LocalFilesystem exists ──────────────────────────────────────

    public function test_local_filesystem_exists_reflects_file_state(): void
    {
        $fs = new LocalFilesystem($this->tempDir);

        $fs->write('check.txt', 'content');
        $this->assertTrue($fs->exists('check.txt'));

        $fs->delete('check.txt');
        $this->assertFalse($fs->exists('check.txt'));
    }

    // ─── 3. LocalFilesystem delete ──────────────────────────────────────

    public function test_local_filesystem_delete_removes_file_from_disk(): void
    {
        $fs = new LocalFilesystem($this->tempDir);

        $fs->write('removable.txt', 'data');
        $this->assertTrue(file_exists($this->tempDir . '/removable.txt'));

        $result = $fs->delete('removable.txt');

        $this->assertTrue($result);
        $this->assertFalse(file_exists($this->tempDir . '/removable.txt'));
    }

    // ─── 4. LocalFilesystem listContents ────────────────────────────────

    public function test_local_filesystem_list_contents_returns_all_files(): void
    {
        $fs = new LocalFilesystem($this->tempDir);

        $fs->write('one.txt', 'first');
        $fs->write('two.txt', 'second');
        $fs->write('three.txt', 'third');

        $contents = $fs->listContents('');
        sort($contents);

        $this->assertCount(3, $contents);
        $this->assertSame(['one.txt', 'three.txt', 'two.txt'], $contents);
    }

    // ─── 5. InMemoryFilesystem CRUD ─────────────────────────────────────

    public function test_in_memory_filesystem_put_get(): void
    {
        $fs = new InMemoryFilesystem();

        $fs->write('mem.txt', 'in-memory content');
        $this->assertSame('in-memory content', $fs->read('mem.txt'));
    }

    public function test_in_memory_filesystem_exists(): void
    {
        $fs = new InMemoryFilesystem();

        $this->assertFalse($fs->exists('missing.txt'));

        $fs->write('present.txt', 'data');
        $this->assertTrue($fs->exists('present.txt'));
    }

    public function test_in_memory_filesystem_delete(): void
    {
        $fs = new InMemoryFilesystem();

        $fs->write('del.txt', 'content');
        $this->assertTrue($fs->exists('del.txt'));

        $result = $fs->delete('del.txt');

        $this->assertTrue($result);
        $this->assertFalse($fs->exists('del.txt'));
    }

    public function test_in_memory_filesystem_delete_nonexistent_returns_false(): void
    {
        $fs = new InMemoryFilesystem();

        $this->assertFalse($fs->delete('nope.txt'));
    }

    // ─── 6. FilesystemManager disks ─────────────────────────────────────

    public function test_filesystem_manager_multiple_disks_are_independent(): void
    {
        $manager = new FilesystemManager();

        $localFs = new LocalFilesystem($this->tempDir);
        $memoryFs = new InMemoryFilesystem();

        $manager->addDisk('local', $localFs);
        $manager->addDisk('memory', $memoryFs);

        // Write different content to each disk
        $manager->disk('local')->write('shared.txt', 'local content');
        $manager->disk('memory')->write('shared.txt', 'memory content');

        // Each disk maintains its own data
        $this->assertSame('local content', $manager->disk('local')->read('shared.txt'));
        $this->assertSame('memory content', $manager->disk('memory')->read('shared.txt'));

        // Verify types
        $this->assertInstanceOf(FilesystemInterface::class, $manager->disk('local'));
        $this->assertInstanceOf(FilesystemInterface::class, $manager->disk('memory'));
    }

    public function test_filesystem_manager_throws_for_unknown_disk(): void
    {
        $manager = new FilesystemManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem disk [nonexistent] is not configured.');

        $manager->disk('nonexistent');
    }

    // ─── 7. Storage facade ──────────────────────────────────────────────

    public function test_storage_facade_put_get_exists_delete(): void
    {
        $manager = new FilesystemManager();
        $manager->addDisk('default', new InMemoryFilesystem());
        Storage::setManager($manager);

        // put
        $result = Storage::put('facade.txt', 'facade content');
        $this->assertTrue($result);

        // get
        $this->assertSame('facade content', Storage::get('facade.txt'));

        // exists
        $this->assertTrue(Storage::exists('facade.txt'));

        // delete
        $this->assertTrue(Storage::delete('facade.txt'));
        $this->assertFalse(Storage::exists('facade.txt'));
    }

    // ─── 8. Full cycle: Storage::put → Storage::get ─────────────────────

    public function test_full_cycle_storage_put_then_get(): void
    {
        $manager = new FilesystemManager();
        $manager->addDisk('default', new InMemoryFilesystem());
        Storage::setManager($manager);

        Storage::put('test.txt', 'hello');

        $this->assertSame('hello', Storage::get('test.txt'));
    }

    public function test_full_cycle_storage_with_local_driver(): void
    {
        $manager = new FilesystemManager();
        $manager->addDisk('default', new LocalFilesystem($this->tempDir));
        Storage::setManager($manager);

        Storage::put('test.txt', 'hello from local');

        $this->assertSame('hello from local', Storage::get('test.txt'));
        $this->assertTrue(Storage::exists('test.txt'));

        // Verify actual file on disk
        $this->assertTrue(file_exists($this->tempDir . '/test.txt'));
        $this->assertSame('hello from local', file_get_contents($this->tempDir . '/test.txt'));

        // Cleanup via facade
        Storage::delete('test.txt');
        $this->assertFalse(Storage::exists('test.txt'));
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
