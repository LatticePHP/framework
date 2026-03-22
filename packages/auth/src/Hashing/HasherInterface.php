<?php

declare(strict_types=1);

namespace Lattice\Auth\Hashing;

interface HasherInterface
{
    /**
     * Hash the given value.
     *
     * @param array<string, mixed> $options
     */
    public function make(string $value, array $options = []): string;

    /**
     * Check the given plain value against a hash.
     */
    public function check(string $value, string $hashedValue): bool;

    /**
     * Check if the given hash needs to be rehashed.
     *
     * @param array<string, mixed> $options
     */
    public function needsRehash(string $hashedValue, array $options = []): bool;
}
