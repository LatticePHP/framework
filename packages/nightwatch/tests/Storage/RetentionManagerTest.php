<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Storage;

use Lattice\Nightwatch\Config\NightwatchConfig;
use Lattice\Nightwatch\Storage\RetentionManager;
use Lattice\Nightwatch\Storage\TimePartitioner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RetentionManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_retention_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_prune_old_directories(): void
    {
        // Create old directories (30 days old)
        $oldPath = $this->tempDir . '/request/2025/01/01/00';
        mkdir($oldPath, 0755, true);
        file_put_contents($oldPath . '/events.ndjson', '{"test":1}' . "\n");

        // Create recent directory
        $recentPath = $this->tempDir . '/request/2026/03/22/14';
        mkdir($recentPath, 0755, true);
        file_put_contents($recentPath . '/events.ndjson', '{"test":2}' . "\n");

        $config = new NightwatchConfig(storagePath: $this->tempDir, devRetentionDays: 7);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'local');

        $deleted = $manager->prune('request');

        $this->assertSame(1, $deleted);
        $this->assertDirectoryDoesNotExist($oldPath);
        $this->assertDirectoryExists($recentPath);
    }

    #[Test]
    public function test_prune_respects_dev_ttl(): void
    {
        // Create a directory 10 days old (dev retention is 7 days)
        $oldPath = $this->tempDir . '/request/2025/01/01/00';
        mkdir($oldPath, 0755, true);
        file_put_contents($oldPath . '/events.ndjson', '{}' . "\n");

        $config = new NightwatchConfig(storagePath: $this->tempDir, devRetentionDays: 7);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'local');

        $deleted = $manager->prune('request');

        $this->assertSame(1, $deleted);
    }

    #[Test]
    public function test_prune_respects_prod_ttl(): void
    {
        // Create a directory 100 days old (prod retention is 90 days)
        $oldPath = $this->tempDir . '/request/2025/01/01/00';
        mkdir($oldPath, 0755, true);
        file_put_contents($oldPath . '/events.ndjson', '{}' . "\n");

        $config = new NightwatchConfig(storagePath: $this->tempDir, prodRetentionDays: 90);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'production');

        $deleted = $manager->prune('request');

        $this->assertSame(1, $deleted);
    }

    #[Test]
    public function test_prune_all_types(): void
    {
        // Create old directories for different types
        foreach (['request', 'query', 'exception'] as $type) {
            $oldPath = $this->tempDir . '/' . $type . '/2025/01/01/00';
            mkdir($oldPath, 0755, true);
            file_put_contents($oldPath . '/events.ndjson', '{}' . "\n");
        }

        $config = new NightwatchConfig(storagePath: $this->tempDir, devRetentionDays: 7);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'local');

        $summary = $manager->pruneAll();

        $this->assertSame(1, $summary['request']);
        $this->assertSame(1, $summary['query']);
        $this->assertSame(1, $summary['exception']);
    }

    #[Test]
    public function test_prune_empty_type_directory(): void
    {
        $config = new NightwatchConfig(storagePath: $this->tempDir, devRetentionDays: 7);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'local');

        $deleted = $manager->prune('nonexistent');

        $this->assertSame(0, $deleted);
    }

    #[Test]
    public function test_prune_cleans_empty_parent_directories(): void
    {
        $oldPath = $this->tempDir . '/request/2025/01/01/00';
        mkdir($oldPath, 0755, true);
        file_put_contents($oldPath . '/events.ndjson', '{}' . "\n");

        $config = new NightwatchConfig(storagePath: $this->tempDir, devRetentionDays: 7);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'local');

        $manager->prune('request');

        // The 2025/01/01 parent directories should also be cleaned up
        $this->assertDirectoryDoesNotExist($this->tempDir . '/request/2025/01/01');
        $this->assertDirectoryDoesNotExist($this->tempDir . '/request/2025/01');
        $this->assertDirectoryDoesNotExist($this->tempDir . '/request/2025');
    }

    #[Test]
    public function test_prune_does_not_remove_directory_with_recent_siblings(): void
    {
        // Two directories on the same day, one old and one recent
        $oldPath = $this->tempDir . '/request/2025/01/01/00';
        mkdir($oldPath, 0755, true);
        file_put_contents($oldPath . '/events.ndjson', '{}' . "\n");

        // The 2026 year dir should survive (not old)
        $recentPath = $this->tempDir . '/request/2026/03/22/14';
        mkdir($recentPath, 0755, true);
        file_put_contents($recentPath . '/events.ndjson', '{}' . "\n");

        $config = new NightwatchConfig(storagePath: $this->tempDir, devRetentionDays: 7);
        $partitioner = new TimePartitioner($this->tempDir);
        $manager = new RetentionManager($config, $partitioner, 'local');

        $manager->prune('request');

        $this->assertDirectoryDoesNotExist($oldPath);
        $this->assertDirectoryExists($recentPath);
        // The request type dir should still exist since it has the 2026 subdirectory
        $this->assertDirectoryExists($this->tempDir . '/request');
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
