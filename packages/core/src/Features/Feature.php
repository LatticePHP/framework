<?php

declare(strict_types=1);

namespace Lattice\Core\Features;

final class Feature
{
    /** @var array<string, callable|bool> */
    private static array $definitions = [];

    /** @var array<string, bool> */
    private static array $store = [];

    public static function define(string $name, callable|bool $resolver): void
    {
        self::$definitions[$name] = $resolver;
    }

    public static function active(string $name, ?object $scope = null): bool
    {
        $key = self::scopeKey($name, $scope);

        if (isset(self::$store[$key])) {
            return self::$store[$key];
        }

        if (!isset(self::$definitions[$name])) {
            return false;
        }

        $resolver = self::$definitions[$name];

        if (is_bool($resolver)) {
            return $resolver;
        }

        return (bool) $resolver($scope);
    }

    public static function for(object $scope): ScopedFeature
    {
        return new ScopedFeature($scope);
    }

    public static function enable(string $name, ?object $scope = null): void
    {
        self::$store[self::scopeKey($name, $scope)] = true;
    }

    public static function disable(string $name, ?object $scope = null): void
    {
        self::$store[self::scopeKey($name, $scope)] = false;
    }

    public static function purge(string $name): void
    {
        foreach (array_keys(self::$store) as $key) {
            if (str_starts_with($key, $name . ':') || $key === $name . ':global') {
                unset(self::$store[$key]);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::$definitions);
    }

    public static function reset(): void
    {
        self::$definitions = [];
        self::$store = [];
    }

    private static function scopeKey(string $name, ?object $scope): string
    {
        if ($scope === null) {
            return $name . ':global';
        }

        $scopeId = $scope instanceof FeatureScopeable
            ? $scope->featureScopeIdentifier()
            : spl_object_id($scope);

        return $name . ':' . $scope::class . ':' . $scopeId;
    }
}
