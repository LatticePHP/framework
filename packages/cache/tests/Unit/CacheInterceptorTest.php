<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests\Unit;

use Lattice\Cache\Attribute\Cacheable;
use Lattice\Cache\CacheInterceptor;
use Lattice\Cache\Driver\ArrayCacheDriver;
use Lattice\Contracts\Context\ExecutionContextInterface;
use PHPUnit\Framework\TestCase;

final class CacheInterceptorTest extends TestCase
{
    public function test_caches_response_on_first_call(): void
    {
        $cache = new ArrayCacheDriver();
        $interceptor = new CacheInterceptor($cache);

        $context = $this->createContextForCacheableMethod();

        $callCount = 0;
        $next = function () use (&$callCount) {
            $callCount++;
            return ['data' => 'expensive_result'];
        };

        $result1 = $interceptor->intercept($context, $next);

        $this->assertSame(['data' => 'expensive_result'], $result1);
        $this->assertSame(1, $callCount);
    }

    public function test_returns_cached_response_on_subsequent_calls(): void
    {
        $cache = new ArrayCacheDriver();
        $interceptor = new CacheInterceptor($cache);

        $context = $this->createContextForCacheableMethod();

        $callCount = 0;
        $next = function () use (&$callCount) {
            $callCount++;
            return ['data' => 'expensive_result'];
        };

        // First call
        $interceptor->intercept($context, $next);

        // Second call should use cache
        $result2 = $interceptor->intercept($context, $next);

        $this->assertSame(['data' => 'expensive_result'], $result2);
        $this->assertSame(1, $callCount, 'Callback should only be invoked once');
    }

    public function test_passes_through_when_no_cacheable_attribute(): void
    {
        $cache = new ArrayCacheDriver();
        $interceptor = new CacheInterceptor($cache);

        $context = $this->createContextForNonCacheableMethod();

        $callCount = 0;
        $next = function () use (&$callCount) {
            $callCount++;
            return 'not-cached';
        };

        $result1 = $interceptor->intercept($context, $next);
        $result2 = $interceptor->intercept($context, $next);

        $this->assertSame('not-cached', $result1);
        $this->assertSame('not-cached', $result2);
        $this->assertSame(2, $callCount, 'Callback should be invoked each time without caching');
    }

    private function createContextForCacheableMethod(): ExecutionContextInterface
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getClass')
            ->willReturn(CacheableTestController::class);
        $context->method('getMethod')
            ->willReturn('cachedAction');

        return $context;
    }

    private function createContextForNonCacheableMethod(): ExecutionContextInterface
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getClass')
            ->willReturn(CacheableTestController::class);
        $context->method('getMethod')
            ->willReturn('nonCachedAction');

        return $context;
    }
}

/**
 * @internal Test helper class
 */
final class CacheableTestController
{
    #[Cacheable(ttl: 300)]
    public function cachedAction(): array
    {
        return ['data' => 'result'];
    }

    public function nonCachedAction(): string
    {
        return 'no-cache';
    }
}
