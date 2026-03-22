<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Storage;

use DateTimeImmutable;
use Lattice\Nightwatch\Storage\TimePartitioner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimePartitionerTest extends TestCase
{
    private TimePartitioner $partitioner;

    protected function setUp(): void
    {
        $this->partitioner = new TimePartitioner('/storage/nightwatch');
    }

    #[Test]
    public function test_path_for_entry(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');

        $path = $this->partitioner->pathForEntry('request', $timestamp);

        $this->assertSame(
            '/storage/nightwatch/request/2026/03/22/14/events.ndjson',
            $path,
        );
    }

    #[Test]
    public function test_path_for_metrics(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');

        $path = $this->partitioner->pathForMetrics($timestamp);

        $this->assertSame(
            '/storage/nightwatch/metrics/2026/03/22/14/aggregates.json',
            $path,
        );
    }

    #[Test]
    public function test_paths_for_range_same_hour(): void
    {
        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 14:59:59');

        $paths = $this->partitioner->pathsForRange($from, $to, 'request');

        $this->assertCount(1, $paths);
        $this->assertSame(
            '/storage/nightwatch/request/2026/03/22/14/events.ndjson',
            $paths[0],
        );
    }

    #[Test]
    public function test_paths_for_range_multiple_hours(): void
    {
        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 16:30:00');

        $paths = $this->partitioner->pathsForRange($from, $to, 'request');

        $this->assertCount(3, $paths);
        $this->assertStringContainsString('/14/', $paths[0]);
        $this->assertStringContainsString('/15/', $paths[1]);
        $this->assertStringContainsString('/16/', $paths[2]);
    }

    #[Test]
    public function test_paths_for_range_spanning_midnight(): void
    {
        $from = new DateTimeImmutable('2026-03-22 23:00:00');
        $to = new DateTimeImmutable('2026-03-23 01:00:00');

        $paths = $this->partitioner->pathsForRange($from, $to, 'request');

        $this->assertCount(3, $paths);
        $this->assertStringContainsString('/22/23/', $paths[0]);
        $this->assertStringContainsString('/23/00/', $paths[1]);
        $this->assertStringContainsString('/23/01/', $paths[2]);
    }

    #[Test]
    public function test_paths_for_range_spanning_months(): void
    {
        $from = new DateTimeImmutable('2026-03-31 23:00:00');
        $to = new DateTimeImmutable('2026-04-01 01:00:00');

        $paths = $this->partitioner->pathsForRange($from, $to, 'query');

        $this->assertCount(3, $paths);
        $this->assertStringContainsString('/03/31/23/', $paths[0]);
        $this->assertStringContainsString('/04/01/00/', $paths[1]);
        $this->assertStringContainsString('/04/01/01/', $paths[2]);
    }

    #[Test]
    public function test_paths_for_day(): void
    {
        $date = new DateTimeImmutable('2026-03-22');

        $paths = $this->partitioner->pathsForDay($date, 'request');

        $this->assertCount(24, $paths);
        $this->assertStringContainsString('/00/', $paths[0]);
        $this->assertStringContainsString('/23/', $paths[23]);
    }

    #[Test]
    public function test_directory_for_entry(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');

        $dir = $this->partitioner->directoryForEntry('request', $timestamp);

        $this->assertSame(
            '/storage/nightwatch/request/2026/03/22/14',
            $dir,
        );
    }

    #[Test]
    public function test_directory_paths_older_than_with_real_dirs(): void
    {
        $tempDir = sys_get_temp_dir() . '/nightwatch_tp_test_' . uniqid();
        $partitioner = new TimePartitioner($tempDir);

        // Create some old directories
        mkdir($tempDir . '/request/2025/01/01/00', 0755, true);
        file_put_contents($tempDir . '/request/2025/01/01/00/events.ndjson', '');

        mkdir($tempDir . '/request/2026/03/22/14', 0755, true);
        file_put_contents($tempDir . '/request/2026/03/22/14/events.ndjson', '');

        $cutoff = new DateTimeImmutable('2026-01-01 00:00:00');
        $paths = $partitioner->directoryPathsOlderThan($cutoff, 'request');

        $this->assertCount(1, $paths);
        $this->assertStringContainsString('/2025/01/01/00', $paths[0]);

        // Clean up
        $this->removeDir($tempDir);
    }

    #[Test]
    public function test_directory_paths_older_than_nonexistent_type(): void
    {
        $tempDir = sys_get_temp_dir() . '/nightwatch_tp_nodir_' . uniqid();
        $partitioner = new TimePartitioner($tempDir);

        $cutoff = new DateTimeImmutable('2026-01-01 00:00:00');
        $paths = $partitioner->directoryPathsOlderThan($cutoff, 'nonexistent');

        $this->assertCount(0, $paths);
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
