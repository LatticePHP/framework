<?php

declare(strict_types=1);

namespace Lattice\Cache\Facades;

use Lattice\Cache\CacheInterface;
use Lattice\Cache\CacheManager;
use Lattice\Cache\Driver\ArrayCacheDriver;

final class Cache
{
    private static ?CacheManager $manager = null;
    private static string $defaultStore = 'default';

    /**
     * Set the CacheManager instance used by the facade.
     */
    public static function setManager(CacheManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Get the CacheManager instance, resolving from the container if not set.
     */
    public static function getManager(): CacheManager
    {
        if (self::$manager === null) {
            self::$manager = \app(CacheManager::class);
        }

        return self::$manager;
    }

    /**
     * Get the default cache store.
     */
    public static function store(string $name = 'default'): CacheInterface
    {
        return self::getManager()->store($name);
    }

    /**
     * Get a value from the cache.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::store(self::$defaultStore)->get($key, $default);
    }

    /**
     * Store a value in the cache.
     */
    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return self::store(self::$defaultStore)->set($key, $value, $ttl);
    }

    /**
     * Determine if a key exists in the cache.
     */
    public static function has(string $key): bool
    {
        return self::store(self::$defaultStore)->has($key);
    }

    /**
     * Remove an item from the cache.
     */
    public static function forget(string $key): bool
    {
        return self::store(self::$defaultStore)->delete($key);
    }

    /**
     * Get a value from the cache, or store and return a default.
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return self::store(self::$defaultStore)->remember($key, $ttl, $callback);
    }

    /**
     * Replace the cache with an in-memory array driver for testing.
     */
    public static function fake(): void
    {
        $manager = new CacheManager();
        $manager->addDriver('default', new ArrayCacheDriver());
        self::$manager = $manager;
        self::$defaultStore = 'default';
    }

    /**
     * Reset the facade instance (useful in tests).
     */
    public static function reset(): void
    {
        self::$manager = null;
        self::$defaultStore = 'default';
    }
}
