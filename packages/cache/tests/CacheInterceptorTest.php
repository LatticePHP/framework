<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests;

use Lattice\Cache\Attribute\Cacheable;
use Lattice\Cache\CacheInterceptor;
use Lattice\Cache\Driver\ArrayCacheDriver;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CacheInterceptorTest extends TestCase
{
    #[Test]
    public function it_implements_interceptor_interface(): void
    {
        $interceptor = new CacheInterceptor(new ArrayCacheDriver());
        $this->assertInstanceOf(InterceptorInterface::class, $interceptor);
    }

    #[Test]
    public function it_caches_result_from_handler(): void
    {
        $cache = new ArrayCacheDriver();
        $interceptor = new CacheInterceptor($cache);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getClass')->willReturn(CachedService::class);
        $context->method('getMethod')->willReturn('getData');

        $callCount = 0;
        $handler = function () use (&$callCount) {
            $callCount++;
            return ['data' => 'value'];
        };

        // First call: should invoke handler
        $result1 = $interceptor->intercept($context, $handler);
        $this->assertSame(['data' => 'value'], $result1);
        $this->assertSame(1, $callCount);

        // Second call: should return cached result
        $result2 = $interceptor->intercept($context, $handler);
        $this->assertSame(['data' => 'value'], $result2);
        $this->assertSame(1, $callCount, 'Handler should not be called again');
    }

    #[Test]
    public function it_passes_through_when_no_cacheable_attribute(): void
    {
        $cache = new ArrayCacheDriver();
        $interceptor = new CacheInterceptor($cache);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getClass')->willReturn(NonCachedService::class);
        $context->method('getMethod')->willReturn('process');

        $callCount = 0;
        $handler = function () use (&$callCount) {
            $callCount++;
            return 'result';
        };

        $interceptor->intercept($context, $handler);
        $interceptor->intercept($context, $handler);

        $this->assertSame(2, $callCount, 'Handler should be called each time without caching');
    }
}

// Test fixtures
class CachedService
{
    #[Cacheable(ttl: 3600, key: 'test.data')]
    public function getData(): array
    {
        return [];
    }
}

class NonCachedService
{
    public function process(): string
    {
        return '';
    }
}
