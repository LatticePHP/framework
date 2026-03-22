<?php

declare(strict_types=1);

namespace Lattice\Cache\Illuminate;

use Illuminate\Cache\CacheManager as BaseCacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;

final class IlluminateCacheManager
{
    private BaseCacheManager $manager;

    public function __construct(array $config)
    {
        $container = new Container();
        $container['config'] = [
            'cache.default' => $config['default'] ?? 'file',
            'cache.stores' => $config['stores'] ?? [],
            'cache.prefix' => $config['prefix'] ?? 'lattice',
        ];

        $this->manager = new BaseCacheManager($container);
    }

    public function store(?string $name = null): Repository
    {
        return $this->manager->store($name);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->get($key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->manager->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->manager->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->manager->has($key);
    }

    /**
     * Access the full Illuminate CacheManager for advanced features:
     * Redis, Memcached, DynamoDB, tags, atomic locks, etc.
     */
    public function getManager(): BaseCacheManager
    {
        return $this->manager;
    }
}
