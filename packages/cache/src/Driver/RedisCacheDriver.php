<?php

declare(strict_types=1);

namespace Lattice\Cache\Driver;

use Lattice\Cache\CacheInterface;
use Lattice\Cache\RedisConfig;
use Redis;

final class RedisCacheDriver implements CacheInterface
{
    private readonly Redis $redis;
    private readonly string $prefix;

    public function __construct(RedisConfig $config)
    {
        $this->prefix = $config->prefix;
        $this->redis = new Redis();
        $this->redis->connect($config->host, $config->port);

        if ($config->password !== null) {
            $this->redis->auth($config->password);
        }

        if ($config->database !== 0) {
            $this->redis->select($config->database);
        }

        if ($config->prefix !== '') {
            $this->redis->setOption(Redis::OPT_PREFIX, $config->prefix);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return $default;
        }

        $decoded = json_decode($value, true);
        return $decoded === null && $value !== 'null' ? $value : $decoded;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $serialized = json_encode($value);

        if ($ttl !== null && $ttl > 0) {
            return $this->redis->setex($key, $ttl, $serialized);
        }

        return $this->redis->set($key, $serialized);
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function clear(): bool
    {
        return $this->redis->flushDB();
    }

    /** @param string[] $keys */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /** @param array<string, mixed> $values */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /** @param string[] $keys */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
