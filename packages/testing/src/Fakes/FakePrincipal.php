<?php

declare(strict_types=1);

namespace Lattice\Testing\Fakes;

use Lattice\Contracts\Context\PrincipalInterface;

/**
 * Simple principal implementation for testing.
 */
final readonly class FakePrincipal implements PrincipalInterface
{
    /**
     * @param list<string> $scopes
     * @param list<string> $roles
     */
    public function __construct(
        private string|int $id = 'test-user-1',
        private string $type = 'user',
        private array $scopes = [],
        private array $roles = [],
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
