<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests\Unit;

use Lattice\Cache\Driver\FakeRedisDriver;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Redis cache driver behavior using FakeRedisDriver (in-memory).
 * This validates the CacheInterface contract without requiring a running Redis server.
 */
final class RedisCacheDriverTest extends TestCase
{
    private FakeRedisDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new FakeRedisDriver();
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertNull($this->driver->get('nonexistent'));
        $this->assertSame('fallback', $this->driver->get('nonexistent', 'fallback'));
    }

    public function test_set_and_get(): void
    {
        $this->driver->set('key', 'value');

        $this->assertSame('value', $this->driver->get('key'));
    }

    public function test_set_with_array_value(): void
    {
        $this->driver->set('config', ['debug' => true, 'level' => 5]);

        $this->assertSame(['debug' => true, 'level' => 5], $this->driver->get('config'));
    }

    public function test_set_with_integer_value(): void
    {
        $this->driver->set('count', 42);

        $this->assertSame(42, $this->driver->get('count'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->driver->has('missing'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->driver->set('exists', 'yes');

        $this->assertTrue($this->driver->has('exists'));
    }

    public function test_delete_removes_key(): void
    {
        $this->driver->set('key', 'value');
        $this->driver->delete('key');

        $this->assertFalse($this->driver->has('key'));
    }

    public function test_clear_removes_all_keys(): void
    {
        $this->driver->set('a', 1);
        $this->driver->set('b', 2);

        $this->driver->clear();

        $this->assertFalse($this->driver->has('a'));
        $this->assertFalse($this->driver->has('b'));
    }

    public function test_get_multiple(): void
    {
        $this->driver->set('x', 10);
        $this->driver->set('y', 20);

        $result = $this->driver->getMultiple(['x', 'y', 'z'], 'default');

        $this->assertSame(10, $result['x']);
        $this->assertSame(20, $result['y']);
        $this->assertSame('default', $result['z']);
    }

    public function test_set_multiple(): void
    {
        $this->driver->setMultiple(['a' => 'alpha', 'b' => 'bravo']);

        $this->assertSame('alpha', $this->driver->get('a'));
        $this->assertSame('bravo', $this->driver->get('b'));
    }

    public function test_delete_multiple(): void
    {
        $this->driver->set('a', 1);
        $this->driver->set('b', 2);
        $this->driver->set('c', 3);

        $this->driver->deleteMultiple(['a', 'c']);

        $this->assertFalse($this->driver->has('a'));
        $this->assertTrue($this->driver->has('b'));
        $this->assertFalse($this->driver->has('c'));
    }

    public function test_remember_caches_callback_result(): void
    {
        $callCount = 0;

        $value1 = $this->driver->remember('computed', 300, function () use (&$callCount) {
            $callCount++;
            return 'expensive';
        });

        $value2 = $this->driver->remember('computed', 300, function () use (&$callCount) {
            $callCount++;
            return 'should not run';
        });

        $this->assertSame('expensive', $value1);
        $this->assertSame('expensive', $value2);
        $this->assertSame(1, $callCount);
    }

    public function test_prefix_isolates_keys(): void
    {
        $driver1 = new FakeRedisDriver('app1:');
        $driver2 = new FakeRedisDriver('app2:');

        $driver1->set('key', 'from-app1');
        $driver2->set('key', 'from-app2');

        $this->assertSame('from-app1', $driver1->get('key'));
        $this->assertSame('from-app2', $driver2->get('key'));
    }

    public function test_set_returns_true(): void
    {
        $this->assertTrue($this->driver->set('k', 'v'));
    }

    public function test_delete_returns_true(): void
    {
        $this->driver->set('k', 'v');
        $this->assertTrue($this->driver->delete('k'));
    }

    public function test_clear_returns_true(): void
    {
        $this->assertTrue($this->driver->clear());
    }
}
