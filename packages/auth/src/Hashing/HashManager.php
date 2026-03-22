<?php

declare(strict_types=1);

namespace Lattice\Auth\Hashing;

final class HashManager implements HasherInterface
{
    /** @var array<string, HasherInterface> */
    private array $hashers = [];

    public function __construct(
        private readonly string $defaultDriver = 'bcrypt',
    ) {
        $this->hashers['bcrypt'] = new BcryptHasher();
        $this->hashers['argon2id'] = new ArgonHasher();
    }

    /**
     * Register a custom hasher driver.
     */
    public function addDriver(string $name, HasherInterface $hasher): void
    {
        $this->hashers[$name] = $hasher;
    }

    /**
     * Get a specific hasher driver.
     */
    public function driver(string $name): HasherInterface
    {
        if (!isset($this->hashers[$name])) {
            throw new \InvalidArgumentException("Hash driver [{$name}] is not registered.");
        }

        return $this->hashers[$name];
    }

    public function make(string $value, array $options = []): string
    {
        return $this->driver($this->defaultDriver)->make($value, $options);
    }

    public function check(string $value, string $hashedValue): bool
    {
        return $this->driver($this->defaultDriver)->check($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver($this->defaultDriver)->needsRehash($hashedValue, $options);
    }
}
