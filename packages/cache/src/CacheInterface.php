<?php

declare(strict_types=1);

namespace Lattice\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    /** @param string[] $keys */
    public function getMultiple(array $keys, mixed $default = null): array;

    /** @param array<string, mixed> $values */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /** @param string[] $keys */
    public function deleteMultiple(array $keys): bool;

    public function remember(string $key, int $ttl, callable $callback): mixed;
}
