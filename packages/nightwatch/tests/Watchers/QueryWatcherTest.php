<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Watchers;

use Lattice\Nightwatch\Storage\StorageManager;
use Lattice\Nightwatch\Watchers\QueryWatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryWatcherTest extends TestCase
{
    private string $tempDir;
    private StorageManager $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_qw_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new StorageManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_capture_sql_query(): void
    {
        $watcher = new QueryWatcher($this->storage);

        $entry = $watcher->capture([
            'sql' => 'SELECT * FROM users WHERE id = 1',
            'bindings' => [1],
            'duration_ms' => 5.2,
            'connection' => 'mysql',
            'caller' => 'app/Http/Controllers/UserController.php:42',
        ]);

        $this->assertSame('query', $entry->type->value);
        $this->assertSame('SELECT * FROM users WHERE id = 1', $entry->data['sql']);
        $this->assertSame([1], $entry->data['bindings']);
        $this->assertSame(5.2, $entry->data['duration_ms']);
        $this->assertSame('mysql', $entry->data['connection']);
        $this->assertFalse($entry->data['slow']);
        $this->assertSame('SELECT', $entry->data['query_type']);
    }

    #[Test]
    public function test_slow_query_detection(): void
    {
        $watcher = new QueryWatcher($this->storage, slowThresholdMs: 100);

        $fastEntry = $watcher->capture([
            'sql' => 'SELECT 1',
            'duration_ms' => 50,
        ]);

        $slowEntry = $watcher->capture([
            'sql' => 'SELECT * FROM big_table',
            'duration_ms' => 200,
        ]);

        $this->assertFalse($fastEntry->data['slow']);
        $this->assertNotContains('slow', $fastEntry->tags);

        $this->assertTrue($slowEntry->data['slow']);
        $this->assertContains('slow', $slowEntry->tags);
    }

    #[Test]
    public function test_query_type_detection(): void
    {
        $watcher = new QueryWatcher($this->storage);

        $select = $watcher->capture(['sql' => 'SELECT * FROM users']);
        $insert = $watcher->capture(['sql' => 'INSERT INTO users (name) VALUES ("test")']);
        $update = $watcher->capture(['sql' => 'UPDATE users SET name = "test" WHERE id = 1']);
        $delete = $watcher->capture(['sql' => 'DELETE FROM users WHERE id = 1']);

        $this->assertSame('SELECT', $select->data['query_type']);
        $this->assertSame('INSERT', $insert->data['query_type']);
        $this->assertSame('UPDATE', $update->data['query_type']);
        $this->assertSame('DELETE', $delete->data['query_type']);

        $this->assertContains('query_type:select', $select->tags);
        $this->assertContains('query_type:insert', $insert->tags);
    }

    #[Test]
    public function test_n1_detection(): void
    {
        $watcher = new QueryWatcher($this->storage);
        $batchId = 'request-123';

        // Same query executed 3 times = N+1
        $entry1 = $watcher->capture(
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1'],
            batchId: $batchId,
        );
        $entry2 = $watcher->capture(
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2'],
            batchId: $batchId,
        );
        $entry3 = $watcher->capture(
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3'],
            batchId: $batchId,
        );

        // First two should not be detected as N+1
        $this->assertFalse($entry1->data['n1_detected']);
        $this->assertFalse($entry2->data['n1_detected']);

        // Third repetition triggers N+1 detection
        $this->assertTrue($entry3->data['n1_detected']);
        $this->assertContains('n+1', $entry3->tags);
    }

    #[Test]
    public function test_reset_counters(): void
    {
        $watcher = new QueryWatcher($this->storage);
        $batchId = 'request-456';

        // Build up N+1
        $watcher->capture(['sql' => 'SELECT * FROM posts WHERE user_id = 1'], batchId: $batchId);
        $watcher->capture(['sql' => 'SELECT * FROM posts WHERE user_id = 2'], batchId: $batchId);
        $watcher->capture(['sql' => 'SELECT * FROM posts WHERE user_id = 3'], batchId: $batchId);

        // Reset and try again
        $watcher->resetCounters();

        $entry = $watcher->capture(
            ['sql' => 'SELECT * FROM posts WHERE user_id = 4'],
            batchId: $batchId,
        );

        $this->assertFalse($entry->data['n1_detected']);
    }

    #[Test]
    public function test_binding_interpolation(): void
    {
        $watcher = new QueryWatcher($this->storage);

        $entry = $watcher->capture([
            'sql' => 'SELECT * FROM users WHERE email = ? AND active = ?',
            'bindings' => ['test@example.com', true],
            'duration_ms' => 2.1,
        ]);

        $this->assertSame(['test@example.com', true], $entry->data['bindings']);
    }

    #[Test]
    public function test_custom_slow_threshold(): void
    {
        $watcher = new QueryWatcher($this->storage, slowThresholdMs: 50);

        $entry = $watcher->capture([
            'sql' => 'SELECT 1',
            'duration_ms' => 75,
        ]);

        $this->assertTrue($entry->data['slow']);
    }

    #[Test]
    public function test_default_connection(): void
    {
        $watcher = new QueryWatcher($this->storage);

        $entry = $watcher->capture(['sql' => 'SELECT 1']);

        $this->assertSame('default', $entry->data['connection']);
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
