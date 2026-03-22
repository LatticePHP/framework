<?php

declare(strict_types=1);

namespace Lattice\Auth\Facades;

use Lattice\Auth\Hashing\HashManager;

final class Hash
{
    private static ?HashManager $instance = null;

    /**
     * Set the HashManager instance used by the facade.
     */
    public static function setInstance(HashManager $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get the HashManager instance, resolving from the container if not set.
     */
    public static function getInstance(): HashManager
    {
        if (self::$instance === null) {
            self::$instance = \app(HashManager::class);
        }

        return self::$instance;
    }

    /**
     * Hash the given value.
     *
     * @param array<string, mixed> $options
     */
    public static function make(string $value, array $options = []): string
    {
        return self::getInstance()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     */
    public static function check(string $value, string $hashedValue): bool
    {
        return self::getInstance()->check($value, $hashedValue);
    }

    /**
     * Check if the given hash needs to be rehashed.
     *
     * @param array<string, mixed> $options
     */
    public static function needsRehash(string $hashedValue, array $options = []): bool
    {
        return self::getInstance()->needsRehash($hashedValue, $options);
    }

    /**
     * Reset the facade instance (useful in tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
