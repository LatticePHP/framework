<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Storage;

use DateTimeImmutable;
use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StorageManagerTest extends TestCase
{
    private string $tempDir;
    private StorageManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_sm_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->manager = new StorageManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_store_and_retrieve_round_trip(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');
        $entry = new Entry(
            type: EntryType::Request,
            data: ['method' => 'GET', 'uri' => '/api/users'],
            tags: ['method:GET'],
            timestamp: $timestamp,
        );

        $this->manager->store($entry);

        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 15:00:00');
        $results = $this->manager->query('request', $from, $to);

        $this->assertCount(1, $results);
        $this->assertSame('GET', $results[0]['data']['method']);
        $this->assertSame('/api/users', $results[0]['data']['uri']);
    }

    #[Test]
    public function test_store_batch(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');
        $entries = [
            new Entry(EntryType::Request, ['method' => 'GET'], timestamp: $timestamp),
            new Entry(EntryType::Request, ['method' => 'POST'], timestamp: $timestamp),
            new Entry(EntryType::Request, ['method' => 'PUT'], timestamp: $timestamp),
        ];

        $this->manager->storeBatch($entries);

        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 15:00:00');
        $results = $this->manager->query('request', $from, $to);

        $this->assertCount(3, $results);
    }

    #[Test]
    public function test_query_with_filter(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');
        $this->manager->store(new Entry(EntryType::Request, ['status' => 200], timestamp: $timestamp));
        $this->manager->store(new Entry(EntryType::Request, ['status' => 404], timestamp: $timestamp));
        $this->manager->store(new Entry(EntryType::Request, ['status' => 500], timestamp: $timestamp));

        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 15:00:00');
        $errors = $this->manager->query(
            'request',
            $from,
            $to,
            filter: fn(array $e) => ($e['data']['status'] ?? 0) >= 400,
        );

        $this->assertCount(2, $errors);
    }

    #[Test]
    public function test_query_with_pagination(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');
        for ($i = 0; $i < 20; $i++) {
            $this->manager->store(new Entry(EntryType::Request, ['index' => $i], timestamp: $timestamp));
        }

        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 15:00:00');

        $page1 = $this->manager->query('request', $from, $to, limit: 5, offset: 0);
        $page2 = $this->manager->query('request', $from, $to, limit: 5, offset: 5);

        $this->assertCount(5, $page1);
        $this->assertCount(5, $page2);
        $this->assertSame(0, $page1[0]['data']['index']);
        $this->assertSame(5, $page2[0]['data']['index']);
    }

    #[Test]
    public function test_query_across_hours(): void
    {
        $this->manager->store(new Entry(
            EntryType::Request,
            ['hour' => 14],
            timestamp: new DateTimeImmutable('2026-03-22 14:30:00'),
        ));
        $this->manager->store(new Entry(
            EntryType::Request,
            ['hour' => 15],
            timestamp: new DateTimeImmutable('2026-03-22 15:30:00'),
        ));

        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 16:00:00');
        $results = $this->manager->query('request', $from, $to);

        $this->assertCount(2, $results);
        $this->assertSame(14, $results[0]['data']['hour']);
        $this->assertSame(15, $results[1]['data']['hour']);
    }

    #[Test]
    public function test_query_latest(): void
    {
        $now = new DateTimeImmutable();
        for ($i = 0; $i < 10; $i++) {
            $this->manager->store(new Entry(
                EntryType::Request,
                ['index' => $i],
                timestamp: $now,
            ));
        }

        $latest = $this->manager->queryLatest('request', 3);

        $this->assertCount(3, $latest);
        // Reverse order - newest first
        $this->assertSame(9, $latest[0]['data']['index']);
        $this->assertSame(8, $latest[1]['data']['index']);
        $this->assertSame(7, $latest[2]['data']['index']);
    }

    #[Test]
    public function test_auto_creates_directories(): void
    {
        $entry = new Entry(
            EntryType::Query,
            ['sql' => 'SELECT 1'],
            timestamp: new DateTimeImmutable('2026-06-15 10:00:00'),
        );

        $this->manager->store($entry);

        $expectedDir = $this->tempDir . '/query/2026/06/15/10';
        $this->assertDirectoryExists($expectedDir);
    }

    #[Test]
    public function test_different_types_stored_separately(): void
    {
        $timestamp = new DateTimeImmutable('2026-03-22 14:30:00');
        $this->manager->store(new Entry(EntryType::Request, ['type_check' => 'request'], timestamp: $timestamp));
        $this->manager->store(new Entry(EntryType::Query, ['type_check' => 'query'], timestamp: $timestamp));

        $from = new DateTimeImmutable('2026-03-22 14:00:00');
        $to = new DateTimeImmutable('2026-03-22 15:00:00');

        $requests = $this->manager->query('request', $from, $to);
        $queries = $this->manager->query('query', $from, $to);

        $this->assertCount(1, $requests);
        $this->assertCount(1, $queries);
        $this->assertSame('request', $requests[0]['data']['type_check']);
        $this->assertSame('query', $queries[0]['data']['type_check']);
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
