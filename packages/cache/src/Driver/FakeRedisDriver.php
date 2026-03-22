<?php

declare(strict_types=1);

namespace Lattice\Cache\Driver;

use Lattice\Cache\CacheInterface;

/**
 * In-memory Redis fake for testing. Delegates to ArrayCacheDriver internally
 * but provides the same CacheInterface API as RedisCacheDriver.
 */
final class FakeRedisDriver implements CacheInterface
{
    private readonly ArrayCacheDriver $inner;
    private readonly string $prefix;

    public function __construct(string $prefix = '')
    {
        $this->inner = new ArrayCacheDriver();
        $this->prefix = $prefix;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($this->prefixed($key), $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->inner->set($this->prefixed($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($this->prefixed($key));
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($this->prefixed($key));
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    /** @param string[] $keys */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $prefixed = array_map(fn (string $k): string => $this->prefixed($k), $keys);
        $results = $this->inner->getMultiple($prefixed, $default);

        // Remap keys back to unprefixed
        $output = [];
        foreach ($keys as $i => $key) {
            $output[$key] = $results[$prefixed[$i]] ?? $default;
        }
        return $output;
    }

    /** @param array<string, mixed> $values */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $prefixed = [];
        foreach ($values as $key => $value) {
            $prefixed[$this->prefixed($key)] = $value;
        }
        return $this->inner->setMultiple($prefixed, $ttl);
    }

    /** @param string[] $keys */
    public function deleteMultiple(array $keys): bool
    {
        $prefixed = array_map(fn (string $k): string => $this->prefixed($k), $keys);
        return $this->inner->deleteMultiple($prefixed);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->inner->remember($this->prefixed($key), $ttl, $callback);
    }

    private function prefixed(string $key): string
    {
        return $this->prefix . $key;
    }
}
