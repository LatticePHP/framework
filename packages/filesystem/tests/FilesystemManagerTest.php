<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Tests;

use Lattice\Filesystem\Driver\InMemoryFilesystem;
use Lattice\Filesystem\FilesystemInterface;
use Lattice\Filesystem\FilesystemManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilesystemManagerTest extends TestCase
{
    #[Test]
    public function it_returns_default_disk(): void
    {
        $fs = new InMemoryFilesystem();
        $manager = new FilesystemManager();
        $manager->addDisk('default', $fs);

        $this->assertSame($fs, $manager->disk());
    }

    #[Test]
    public function it_returns_named_disk(): void
    {
        $local = new InMemoryFilesystem();
        $s3 = new InMemoryFilesystem();

        $manager = new FilesystemManager();
        $manager->addDisk('local', $local);
        $manager->addDisk('s3', $s3);

        $this->assertSame($local, $manager->disk('local'));
        $this->assertSame($s3, $manager->disk('s3'));
    }

    #[Test]
    public function it_throws_for_unknown_disk(): void
    {
        $manager = new FilesystemManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->disk('nonexistent');
    }
}
