<?php

declare(strict_types=1);

namespace Lattice\Authorization;

use Lattice\Authorization\Exceptions\ForbiddenException;
use Lattice\Contracts\Context\PrincipalInterface;

final class Gate
{
    /** @var array<string, callable> */
    private array $abilities = [];

    /** @var list<callable> */
    private array $beforeCallbacks = [];

    private ?PrincipalInterface $userContext = null;

    public function define(string $ability, callable $check): void
    {
        $this->abilities[$ability] = $check;
    }

    /**
     * Register a callback that runs before all ability checks.
     * If it returns true, access is granted. If false, denied. If null, continue to normal check.
     */
    public function before(callable $callback): void
    {
        $this->beforeCallbacks[] = $callback;
    }

    /**
     * Create a gate instance scoped to a specific user/principal.
     */
    public function forUser(PrincipalInterface $user): self
    {
        $gate = clone $this;
        $gate->userContext = $user;

        return $gate;
    }

    public function allows(PrincipalInterface|null $principal, string $ability, mixed ...$args): bool
    {
        $principal = $principal ?? $this->userContext;

        if ($principal === null) {
            return false;
        }

        // Run before callbacks
        foreach ($this->beforeCallbacks as $callback) {
            $result = $callback($principal, $ability, ...$args);
            if ($result === true) {
                return true;
            }
            if ($result === false) {
                return false;
            }
            // null means continue
        }

        if (!isset($this->abilities[$ability])) {
            return false;
        }

        return (bool) ($this->abilities[$ability])($principal, ...$args);
    }

    public function denies(PrincipalInterface|null $principal, string $ability, mixed ...$args): bool
    {
        return !$this->allows($principal, $ability, ...$args);
    }

    /**
     * Authorize an ability — throws ForbiddenException if denied.
     *
     * @throws ForbiddenException
     */
    public function authorize(PrincipalInterface|null $principal, string $ability, mixed ...$args): void
    {
        if ($this->denies($principal, $ability, ...$args)) {
            throw new ForbiddenException("Unauthorized to perform [{$ability}].");
        }
    }

    public function has(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return array_keys($this->abilities);
    }
}
