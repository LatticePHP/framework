<?php

declare(strict_types=1);

namespace Lattice\Contracts\Context;

interface PrincipalInterface
{
    public function getId(): string|int;

    public function getType(): string;

    /** @return array<string> */
    public function getScopes(): array;

    /** @return array<string> */
    public function getRoles(): array;

    public function hasScope(string $scope): bool;

    public function hasRole(string $role): bool;
}
