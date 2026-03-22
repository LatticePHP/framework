<?php

declare(strict_types=1);

namespace Lattice\Core\Support;

final class Str
{
    /** @var array<string, string> */
    private static array $studlyCache = [];

    /** @var array<string, string> */
    private static array $camelCache = [];

    /** @var array<string, string> */
    private static array $snakeCache = [];

    /**
     * Convert a value to studly caps case (foo_bar -> FooBar).
     */
    public static function studly(string $value): string
    {
        if (isset(self::$studlyCache[$value])) {
            return self::$studlyCache[$value];
        }

        $result = str_replace(['-', '_'], ' ', $value);
        $result = ucwords($result);
        $result = str_replace(' ', '', $result);

        return self::$studlyCache[$value] = $result;
    }

    /**
     * Convert a value to camel case (foo_bar -> fooBar).
     */
    public static function camel(string $value): string
    {
        if (isset(self::$camelCache[$value])) {
            return self::$camelCache[$value];
        }

        return self::$camelCache[$value] = lcfirst(self::studly($value));
    }

    /**
     * Convert a string to snake case (FooBar -> foo_bar).
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value . $delimiter;

        if (isset(self::$snakeCache[$key])) {
            return self::$snakeCache[$key];
        }

        $result = preg_replace('/([a-z])([A-Z])/', '$1' . $delimiter . '$2', $value);
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1' . $delimiter . '$2', $result);
        $result = strtolower((string) $result);

        return self::$snakeCache[$key] = $result;
    }

    /**
     * Convert a string to kebab case (FooBar -> foo-bar).
     */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value) ?? '';
        $value = preg_replace('/[\s-]+/', $separator, $value) ?? '';
        return trim($value, $separator);
    }

    /**
     * Determine if a given string starts with a given substring.
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string contains a given substring.
     */
    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a UUID (version 4).
     */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);

        // Set version to 4
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        // Set variant to RFC 4122
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );
    }

    /**
     * Generate a random string of the given length.
     */
    public static function random(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $charsLength = strlen($chars);
        $result = '';

        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[ord($bytes[$i]) % $charsLength];
        }

        return $result;
    }

    /**
     * Convert the given string to upper-case.
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Convert the given string to lower-case.
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert the given string to title case.
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Limit the number of characters in a string.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }

    /**
     * Get the plural form of an English word (basic implementation).
     */
    public static function plural(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $lastChar = strtolower(substr($value, -1));
        $lastTwo = strtolower(substr($value, -2));

        if ($lastTwo === 'ss' || $lastTwo === 'sh' || $lastTwo === 'ch' || $lastTwo === 'us'
            || $lastChar === 'x' || $lastChar === 'z') {
            return $value . 'es';
        }

        if ($lastChar === 'y' && !in_array(strtolower(substr($value, -2, 1)), ['a', 'e', 'i', 'o', 'u'], true)) {
            return substr($value, 0, -1) . 'ies';
        }

        if ($lastChar === 's') {
            return $value;
        }

        return $value . 's';
    }

    /**
     * Get the singular form of an English word (basic implementation).
     */
    public static function singular(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $lastThree = strtolower(substr($value, -3));
        $lastTwo = strtolower(substr($value, -2));

        if ($lastThree === 'ies') {
            return substr($value, 0, -3) . 'y';
        }

        if ($lastThree === 'ses' || $lastThree === 'hes' || $lastThree === 'xes' || $lastThree === 'zes') {
            return substr($value, 0, -2);
        }

        if ($lastTwo === 'ss') {
            return $value;
        }

        if (str_ends_with($value, 's') && !str_ends_with($value, 'ss')) {
            return substr($value, 0, -1);
        }

        return $value;
    }
}
