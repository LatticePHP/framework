<?php

declare(strict_types=1);

namespace Lattice\Cache\Driver;

use Lattice\Cache\CacheInterface;

final class ArrayCacheDriver implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiry: ?int}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expiry = null;
        if ($ttl !== null) {
            $expiry = time() + $ttl;
        }

        $this->store[$key] = [
            'value' => $value,
            'expiry' => $expiry,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }

        $entry = $this->store[$key];
        if ($entry['expiry'] !== null && $entry['expiry'] <= time()) {
            unset($this->store[$key]);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

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
