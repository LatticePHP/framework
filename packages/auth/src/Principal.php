<?php

declare(strict_types=1);

namespace Lattice\Auth;

use Lattice\Contracts\Context\PrincipalInterface;

final class Principal implements PrincipalInterface
{
    public function __construct(
        private readonly string|int $id,
        private readonly string $type = 'user',
        private readonly array $scopes = [],
        private readonly array $roles = [],
        private readonly array $claims = [],
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @return array<string> */
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

    /** @return array<string, mixed> */
    public function getClaims(): array
    {
        return $this->claims;
    }

    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }
}
