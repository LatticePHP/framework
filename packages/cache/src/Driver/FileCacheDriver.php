<?php

declare(strict_types=1);

namespace Lattice\Cache\Driver;

use Lattice\Cache\CacheInterface;

final class FileCacheDriver implements CacheInterface
{
    public function __construct(
        private readonly string $directory,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return $default;
        }

        $data = unserialize(file_get_contents($path));

        if ($data['expiry'] !== null && $data['expiry'] <= time()) {
            @unlink($path);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expiry = null;
        if ($ttl !== null) {
            $expiry = time() + $ttl;
        }

        $data = serialize([
            'value' => $value,
            'expiry' => $expiry,
        ]);

        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return file_put_contents($path, $data) !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            return unlink($path);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->clearDirectory($this->directory);
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

    private function path(string $key): string
    {
        $hash = sha1($key);
        return $this->directory . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->clearDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
