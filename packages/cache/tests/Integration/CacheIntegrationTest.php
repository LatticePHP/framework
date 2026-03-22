<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests\Integration;

use Lattice\Cache\CacheInterface;
use Lattice\Cache\CacheManager;
use Lattice\Cache\Driver\ArrayCacheDriver;
use Lattice\Cache\Driver\FileCacheDriver;
use Lattice\Cache\Facades\Cache;
use PHPUnit\Framework\TestCase;

final class CacheIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lattice_cache_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        Cache::reset();
    }

    protected function tearDown(): void
    {
        Cache::reset();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    // ─── 1. ArrayCacheDriver put→get ────────────────────────────────────

    public function test_array_cache_driver_put_get_returns_same_value(): void
    {
        $driver = new ArrayCacheDriver();

        $driver->set('greeting', 'hello world');

        $this->assertSame('hello world', $driver->get('greeting'));
    }

    // ─── 2. ArrayCacheDriver TTL expires ────────────────────────────────

    public function test_array_cache_driver_ttl_expires_returns_null(): void
    {
        $driver = new ArrayCacheDriver();

        $driver->set('ephemeral', 'gone soon', 1);

        // Value should exist immediately
        $this->assertSame('gone soon', $driver->get('ephemeral'));

        // Wait for TTL to expire
        sleep(2);

        $this->assertNull($driver->get('ephemeral'));
    }

    // ─── 3. ArrayCacheDriver forget ─────────────────────────────────────

    public function test_array_cache_driver_forget_removes_key(): void
    {
        $driver = new ArrayCacheDriver();

        $driver->set('to_remove', 'value');
        $this->assertSame('value', $driver->get('to_remove'));

        $driver->delete('to_remove');

        $this->assertNull($driver->get('to_remove'));
    }

    // ─── 4. ArrayCacheDriver has ────────────────────────────────────────

    public function test_array_cache_driver_has_returns_correct_state(): void
    {
        $driver = new ArrayCacheDriver();

        $driver->set('exists', 'yes');
        $this->assertTrue($driver->has('exists'));

        $driver->delete('exists');
        $this->assertFalse($driver->has('exists'));
    }

    // ─── 5. FileCacheDriver put→get ─────────────────────────────────────

    public function test_file_cache_driver_put_get_returns_same_value(): void
    {
        $driver = new FileCacheDriver($this->tempDir);

        $driver->set('file_key', 'file_value');

        $this->assertSame('file_value', $driver->get('file_key'));
    }

    // ─── 6. FileCacheDriver forget ──────────────────────────────────────

    public function test_file_cache_driver_forget_removes_file(): void
    {
        $driver = new FileCacheDriver($this->tempDir);

        $driver->set('deletable', 'will be removed');
        $this->assertSame('will be removed', $driver->get('deletable'));

        $driver->delete('deletable');

        $this->assertNull($driver->get('deletable'));
    }

    // ─── 7. CacheManager multiple stores ────────────────────────────────

    public function test_cache_manager_multiple_stores_are_independent(): void
    {
        $manager = new CacheManager();

        $arrayDriver = new ArrayCacheDriver();
        $fileDriver = new FileCacheDriver($this->tempDir);

        $manager->addDriver('array', $arrayDriver);
        $manager->addDriver('file', $fileDriver);

        // Store different values in each store
        $manager->store('array')->set('key', 'array_value');
        $manager->store('file')->set('key', 'file_value');

        // Each store maintains its own data
        $this->assertSame('array_value', $manager->store('array')->get('key'));
        $this->assertSame('file_value', $manager->store('file')->get('key'));

        // Switching between stores works
        $this->assertInstanceOf(CacheInterface::class, $manager->store('array'));
        $this->assertInstanceOf(CacheInterface::class, $manager->store('file'));
    }

    // ─── 8. Cache::remember() miss ──────────────────────────────────────

    public function test_cache_remember_miss_calls_callback_and_stores_value(): void
    {
        $manager = new CacheManager();
        $manager->addDriver('default', new ArrayCacheDriver());
        Cache::setManager($manager);

        $callbackInvoked = false;

        $result = Cache::remember('computed', 60, function () use (&$callbackInvoked) {
            $callbackInvoked = true;
            return 'computed_value';
        });

        $this->assertTrue($callbackInvoked);
        $this->assertSame('computed_value', $result);

        // Value should now be stored
        $this->assertSame('computed_value', Cache::get('computed'));
    }

    // ─── 9. Cache::remember() hit ───────────────────────────────────────

    public function test_cache_remember_hit_does_not_call_callback(): void
    {
        $manager = new CacheManager();
        $manager->addDriver('default', new ArrayCacheDriver());
        Cache::setManager($manager);

        // Pre-populate cache
        Cache::put('preloaded', 'cached_value');

        $callbackInvoked = false;

        $result = Cache::remember('preloaded', 60, function () use (&$callbackInvoked) {
            $callbackInvoked = true;
            return 'should_not_be_used';
        });

        $this->assertFalse($callbackInvoked);
        $this->assertSame('cached_value', $result);
    }

    // ─── 10. Cache facade static methods ────────────────────────────────

    public function test_cache_facade_static_methods_work(): void
    {
        $manager = new CacheManager();
        $manager->addDriver('default', new ArrayCacheDriver());
        Cache::setManager($manager);

        // put + get
        Cache::put('facade_key', 'facade_value');
        $this->assertSame('facade_value', Cache::get('facade_key'));

        // has
        $this->assertTrue(Cache::has('facade_key'));

        // forget
        Cache::forget('facade_key');
        $this->assertFalse(Cache::has('facade_key'));
        $this->assertNull(Cache::get('facade_key'));
    }

    // ─── 11. Full cycle: controller-like store → second request read ────

    public function test_full_cycle_store_then_read_from_cache(): void
    {
        $manager = new CacheManager();
        $arrayDriver = new ArrayCacheDriver();
        $manager->addDriver('default', $arrayDriver);
        Cache::setManager($manager);

        // Simulate first request: controller stores user data in cache
        $userData = ['id' => 42, 'name' => 'Alice', 'email' => 'alice@example.com'];
        Cache::put('user:42', serialize($userData), 300);

        $this->assertTrue(Cache::has('user:42'));

        // Simulate second request: another controller reads from cache
        // (same CacheManager instance, simulating shared state)
        $cached = unserialize(Cache::get('user:42'));

        $this->assertSame(42, $cached['id']);
        $this->assertSame('Alice', $cached['name']);
        $this->assertSame('alice@example.com', $cached['email']);

        // Simulate cache invalidation after user update
        Cache::forget('user:42');
        $this->assertFalse(Cache::has('user:42'));
        $this->assertNull(Cache::get('user:42'));
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
