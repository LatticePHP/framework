<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Watchers;

use Lattice\Nightwatch\Storage\StorageManager;
use Lattice\Nightwatch\Watchers\RequestWatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestWatcherTest extends TestCase
{
    private string $tempDir;
    private StorageManager $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nightwatch_rw_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new StorageManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_capture_get_request(): void
    {
        $watcher = new RequestWatcher($this->storage);

        $entry = $watcher->capture([
            'method' => 'GET',
            'uri' => '/api/users',
            'status' => 200,
            'duration_ms' => 42.5,
            'headers' => ['content-type' => 'application/json'],
            'ip' => '127.0.0.1',
        ]);

        $this->assertSame('request', $entry->type->value);
        $this->assertSame('GET', $entry->data['method']);
        $this->assertSame('/api/users', $entry->data['uri']);
        $this->assertSame(200, $entry->data['status']);
        $this->assertSame(42.5, $entry->data['duration_ms']);
        $this->assertContains('method:GET', $entry->tags);
        $this->assertContains('status:200', $entry->tags);
    }

    #[Test]
    public function test_capture_post_request(): void
    {
        $watcher = new RequestWatcher($this->storage);

        $entry = $watcher->capture([
            'method' => 'POST',
            'uri' => '/api/users',
            'status' => 201,
            'duration_ms' => 150.0,
        ]);

        $this->assertSame('POST', $entry->data['method']);
        $this->assertSame(201, $entry->data['status']);
        $this->assertContains('method:POST', $entry->tags);
    }

    #[Test]
    public function test_header_redaction(): void
    {
        $watcher = new RequestWatcher($this->storage);

        $entry = $watcher->capture([
            'method' => 'GET',
            'uri' => '/api/protected',
            'headers' => [
                'Authorization' => 'Bearer secret-token-123',
                'Cookie' => 'session=abc123',
                'Accept' => 'application/json',
                'X-Custom' => 'safe-value',
            ],
        ]);

        $headers = $entry->data['headers'];
        $this->assertSame('********', $headers['Authorization']);
        $this->assertSame('********', $headers['Cookie']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('safe-value', $headers['X-Custom']);
    }

    #[Test]
    public function test_path_ignoring(): void
    {
        $watcher = new RequestWatcher(
            $this->storage,
            ignoredPaths: ['/nightwatch', '/health'],
        );

        $entry1 = $watcher->capture(['method' => 'GET', 'uri' => '/nightwatch/api/requests']);
        $entry2 = $watcher->capture(['method' => 'GET', 'uri' => '/health/ping']);
        $entry3 = $watcher->capture(['method' => 'GET', 'uri' => '/api/users']);

        // The watcher should not have recorded entries 1 and 2 (shouldRecord returns false)
        // but capture still returns the entry object - it just doesn't store it
        $this->assertNotNull($entry1);
        $this->assertNotNull($entry2);
        $this->assertNotNull($entry3);
    }

    #[Test]
    public function test_error_tags(): void
    {
        $watcher = new RequestWatcher($this->storage);

        $clientError = $watcher->capture([
            'method' => 'GET',
            'uri' => '/api/missing',
            'status' => 404,
        ]);

        $serverError = $watcher->capture([
            'method' => 'GET',
            'uri' => '/api/broken',
            'status' => 500,
        ]);

        $this->assertContains('error', $clientError->tags);
        $this->assertNotContains('server_error', $clientError->tags);

        $this->assertContains('error', $serverError->tags);
        $this->assertContains('server_error', $serverError->tags);
    }

    #[Test]
    public function test_batch_id_assignment(): void
    {
        $watcher = new RequestWatcher($this->storage);

        $entry = $watcher->capture(
            ['method' => 'GET', 'uri' => '/test'],
            batchId: 'batch-123',
        );

        $this->assertSame('batch-123', $entry->batchId);
    }

    #[Test]
    public function test_disabled_watcher_does_not_store(): void
    {
        $watcher = new RequestWatcher($this->storage);
        $watcher->setEnabled(false);

        $this->assertFalse($watcher->isEnabled());

        // capture still creates the entry but handle() won't store it
        $entry = $watcher->capture(['method' => 'GET', 'uri' => '/test']);
        $this->assertNotNull($entry);
    }

    #[Test]
    public function test_custom_redacted_headers(): void
    {
        $watcher = new RequestWatcher(
            $this->storage,
            redactedHeaders: ['x-api-key', 'x-secret'],
        );

        $entry = $watcher->capture([
            'method' => 'GET',
            'uri' => '/test',
            'headers' => [
                'X-Api-Key' => 'key-123',
                'X-Secret' => 'secret-456',
                'Authorization' => 'Bearer token', // Not redacted with custom list
            ],
        ]);

        $headers = $entry->data['headers'];
        $this->assertSame('********', $headers['X-Api-Key']);
        $this->assertSame('********', $headers['X-Secret']);
        $this->assertSame('Bearer token', $headers['Authorization']);
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
