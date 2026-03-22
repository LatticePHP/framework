<?php

declare(strict_types=1);

namespace Lattice\Auth\Hashing;

final class ArgonHasher implements HasherInterface
{
    public function __construct(
        private readonly int $memory = 65536,
        private readonly int $time = 4,
        private readonly int $threads = 1,
    ) {}

    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Argon2id hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, PASSWORD_ARGON2ID, [
            'memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,
        ]);
    }
}
