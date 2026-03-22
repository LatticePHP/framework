<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests;

use Lattice\Cache\CacheInterface;
use Lattice\Cache\Driver\ArrayCacheDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayCacheDriverTest extends TestCase
{
    private ArrayCacheDriver $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCacheDriver();
    }

    #[Test]
    public function it_implements_cache_interface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, $this->cache);
    }

    #[Test]
    public function it_returns_default_when_key_not_found(): void
    {
        $this->assertNull($this->cache->get('missing'));
        $this->assertSame('fallback', $this->cache->get('missing', 'fallback'));
    }

    #[Test]
    public function it_sets_and_gets_value(): void
    {
        $this->assertTrue($this->cache->set('name', 'Lattice'));
        $this->assertSame('Lattice', $this->cache->get('name'));
    }

    #[Test]
    public function it_checks_has(): void
    {
        $this->assertFalse($this->cache->has('key'));
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->has('key'));
    }

    #[Test]
    public function it_deletes_a_key(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->delete('key'));
        $this->assertFalse($this->cache->has('key'));
    }

    #[Test]
    public function it_clears_all_keys(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
    }

    #[Test]
    public function it_respects_ttl(): void
    {
        // Set with 1-second TTL
        $this->cache->set('temp', 'data', 1);
        $this->assertSame('data', $this->cache->get('temp'));

        // Simulate expiry by setting with TTL of 0 (immediate expiry)
        $this->cache->set('expired', 'gone', -1);
        $this->assertNull($this->cache->get('expired'));
    }

    #[Test]
    public function it_gets_multiple_keys(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);

        $result = $this->cache->getMultiple(['a', 'b', 'c'], 'default');

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 'default'], $result);
    }

    #[Test]
    public function it_sets_multiple_keys(): void
    {
        $this->assertTrue($this->cache->setMultiple(['x' => 10, 'y' => 20]));
        $this->assertSame(10, $this->cache->get('x'));
        $this->assertSame(20, $this->cache->get('y'));
    }

    #[Test]
    public function it_deletes_multiple_keys(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->cache->set('c', 3);

        $this->assertTrue($this->cache->deleteMultiple(['a', 'c']));
        $this->assertFalse($this->cache->has('a'));
        $this->assertTrue($this->cache->has('b'));
        $this->assertFalse($this->cache->has('c'));
    }

    #[Test]
    public function it_remembers_value_using_callback(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return 'computed';
        };

        $result1 = $this->cache->remember('key', 3600, $callback);
        $result2 = $this->cache->remember('key', 3600, $callback);

        $this->assertSame('computed', $result1);
        $this->assertSame('computed', $result2);
        $this->assertSame(1, $callCount, 'Callback should only be called once');
    }
}
