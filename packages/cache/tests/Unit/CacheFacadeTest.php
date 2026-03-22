<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests\Unit;

use Lattice\Cache\CacheManager;
use Lattice\Cache\Driver\ArrayCacheDriver;
use Lattice\Cache\Facades\Cache;
use PHPUnit\Framework\TestCase;

final class CacheFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::reset();

        $manager = new CacheManager();
        $manager->addDriver('default', new ArrayCacheDriver());
        Cache::setManager($manager);
    }

    protected function tearDown(): void
    {
        Cache::reset();
        parent::tearDown();
    }

    public function test_put_and_get(): void
    {
        Cache::put('key', 'value');

        $this->assertSame('value', Cache::get('key'));
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertSame('default', Cache::get('missing', 'default'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        Cache::put('exists', true);

        $this->assertTrue(Cache::has('exists'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse(Cache::has('missing'));
    }

    public function test_forget_removes_key(): void
    {
        Cache::put('key', 'value');
        Cache::forget('key');

        $this->assertFalse(Cache::has('key'));
    }

    public function test_remember_stores_and_returns_value(): void
    {
        $callCount = 0;

        $value = Cache::remember('computed', 3600, function () use (&$callCount) {
            $callCount++;
            return 'expensive_result';
        });

        $this->assertSame('expensive_result', $value);
        $this->assertSame(1, $callCount);

        // Second call should return cached value without invoking callback
        $value2 = Cache::remember('computed', 3600, function () use (&$callCount) {
            $callCount++;
            return 'should_not_reach';
        });

        $this->assertSame('expensive_result', $value2);
        $this->assertSame(1, $callCount);
    }

    public function test_remember_returns_cached_on_subsequent_calls(): void
    {
        $invocations = 0;

        Cache::remember('key', 60, function () use (&$invocations) {
            $invocations++;
            return 42;
        });

        $result = Cache::remember('key', 60, function () use (&$invocations) {
            $invocations++;
            return 99;
        });

        $this->assertSame(42, $result);
        $this->assertSame(1, $invocations);
    }

    public function test_fake_provides_working_array_cache(): void
    {
        Cache::fake();

        Cache::put('test', 'faked');
        $this->assertSame('faked', Cache::get('test'));
    }
}
