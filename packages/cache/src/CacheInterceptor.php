<?php

declare(strict_types=1);

namespace Lattice\Cache;

use Lattice\Cache\Attribute\Cacheable;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;

final class CacheInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $cacheable = $this->getCacheableAttribute($context);

        if ($cacheable === null) {
            return $next($context);
        }

        $cacheKey = $cacheable->key ?? $context->getClass() . '::' . $context->getMethod();

        return $this->cache->remember($cacheKey, $cacheable->ttl, function () use ($next, $context) {
            return $next($context);
        });
    }

    private function getCacheableAttribute(ExecutionContextInterface $context): ?Cacheable
    {
        try {
            $reflection = new \ReflectionMethod($context->getClass(), $context->getMethod());
            $attributes = $reflection->getAttributes(Cacheable::class);

            if (empty($attributes)) {
                return null;
            }

            return $attributes[0]->newInstance();
        } catch (\ReflectionException) {
            return null;
        }
    }
}
