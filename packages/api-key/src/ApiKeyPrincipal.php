<?php

declare(strict_types=1);

namespace Lattice\ApiKey;

use Lattice\Contracts\Context\PrincipalInterface;

final readonly class ApiKeyPrincipal implements PrincipalInterface
{
    public function __construct(
        private string $id,
        private string $name,
        private array $scopes = [],
        private array $metadata = [],
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return 'api_key';
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @return array<string> */
    public function getRoles(): array
    {
        return [];
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function hasRole(string $role): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
