<?php

declare(strict_types=1);

namespace Lattice\Auth\Hashing;

final class BcryptHasher implements HasherInterface
{
    public function __construct(
        private readonly int $rounds = 12,
    ) {}

    public function make(string $value, array $options = []): string
    {
        $rounds = $options['rounds'] ?? $this->rounds;

        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $rounds,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Bcrypt hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        $rounds = $options['rounds'] ?? $this->rounds;

        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $rounds,
        ]);
    }
}
