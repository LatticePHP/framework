<?php

declare(strict_types=1);

namespace Lattice\Core\Support;

final class Arr
{
    /**
     * Get an item from an array using "dot" notation.
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Check if an item exists in an array using "dot" notation.
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Remove an item from an array using "dot" notation.
     */
    public static function forget(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                unset($current[$segment]);
                return;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     */
    public static function flatten(array $array, int|float $depth = INF): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, self::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    /**
     * Get a subset of the items from the given array.
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Get all of the given array except for a specified array of keys.
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Return the first element in an array passing a given truth test.
     */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if ($array === []) {
                return $default;
            }
            return reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Return the last element in an array passing a given truth test.
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if ($array === []) {
                return $default;
            }
            return end($array);
        }

        $result = $default;

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $result = $value;
            }
        }

        return $result;
    }

    /**
     * If the given value is not an array, wrap it in one.
     */
    public static function wrap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $value === null ? [] : [$value];
    }

    /**
     * Pluck an array of values from an array.
     */
    public static function pluck(array $array, string $key): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && array_key_exists($key, $item)) {
                $result[] = $item[$key];
            } elseif (is_object($item) && property_exists($item, $key)) {
                $result[] = $item->{$key};
            }
        }

        return $result;
    }
}
