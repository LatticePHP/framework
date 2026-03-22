<?php

declare(strict_types=1);

namespace Lattice\Core\Config;

final class ConfigRepository
{
    /** @var array<string, mixed> */
    private array $items;

    /**
     * @param array<string, mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (str_contains($key, '.')) {
            return $this->getNestedValue($key, $default);
        }

        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (!str_contains($key, '.')) {
            $this->items[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $array = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $array[$segment] = $value;
            } else {
                if (!isset($array[$segment]) || !is_array($array[$segment])) {
                    $array[$segment] = [];
                }
                $array = &$array[$segment];
            }
        }
    }

    public function has(string $key): bool
    {
        if (!str_contains($key, '.')) {
            return array_key_exists($key, $this->items);
        }

        $segments = explode('.', $key);
        $array = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function loadFromDirectory(string $directory): void
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    private function getNestedValue(string $key, mixed $default): mixed
    {
        $segments = explode('.', $key);
        $array = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}
