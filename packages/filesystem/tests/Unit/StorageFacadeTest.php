<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Tests\Unit;

use Lattice\Filesystem\Driver\InMemoryFilesystem;
use Lattice\Filesystem\Facades\Storage;
use Lattice\Filesystem\FilesystemManager;
use PHPUnit\Framework\TestCase;

final class StorageFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::reset();

        $manager = new FilesystemManager();
        $manager->addDisk('default', new InMemoryFilesystem());
        Storage::setManager($manager);
    }

    protected function tearDown(): void
    {
        Storage::reset();
        parent::tearDown();
    }

    public function test_put_and_get(): void
    {
        Storage::put('test.txt', 'Hello World');

        $this->assertSame('Hello World', Storage::get('test.txt'));
    }

    public function test_exists_returns_true_for_existing_file(): void
    {
        Storage::put('exists.txt', 'content');

        $this->assertTrue(Storage::exists('exists.txt'));
    }

    public function test_exists_returns_false_for_missing_file(): void
    {
        $this->assertFalse(Storage::exists('missing.txt'));
    }

    public function test_delete_removes_file(): void
    {
        Storage::put('delete-me.txt', 'content');
        $result = Storage::delete('delete-me.txt');

        $this->assertTrue($result);
        $this->assertFalse(Storage::exists('delete-me.txt'));
    }

    public function test_url_returns_path(): void
    {
        Storage::put('docs/readme.txt', 'content');

        $url = Storage::url('docs/readme.txt');

        $this->assertSame('docs/readme.txt', $url);
    }

    public function test_fake_returns_in_memory_filesystem(): void
    {
        $fake = Storage::fake();

        $this->assertInstanceOf(InMemoryFilesystem::class, $fake);

        Storage::put('faked.txt', 'fake data');
        $this->assertSame('fake data', Storage::get('faked.txt'));
    }

    public function test_disk_returns_filesystem_interface(): void
    {
        $disk = Storage::disk();

        $this->assertInstanceOf(InMemoryFilesystem::class, $disk);
    }
}
