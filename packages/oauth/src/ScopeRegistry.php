<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class ScopeRegistry
{
    /** @var array<string, string> scope => description */
    private array $scopes = [];

    /** @var array<string> */
    private array $defaultScopes = [];

    public function register(string $scope, string $description): void
    {
        $this->scopes[$scope] = $description;
    }

    /**
     * @param array<string> $scopes
     */
    public function setDefaultScopes(array $scopes): void
    {
        foreach ($scopes as $scope) {
            if (!isset($this->scopes[$scope])) {
                throw new \InvalidArgumentException("Cannot set unknown scope as default: {$scope}");
            }
        }

        $this->defaultScopes = $scopes;
    }

    /**
     * @return array<string>
     */
    public function getDefaultScopes(): array
    {
        return $this->defaultScopes;
    }

    public function has(string $scope): bool
    {
        return isset($this->scopes[$scope]);
    }

    public function getDescription(string $scope): ?string
    {
        return $this->scopes[$scope] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->scopes;
    }

    /**
     * Validates requested scopes against the registry and the client's allowed scopes.
     *
     * @param array<string> $requested Scopes requested in the authorization request
     * @param array<string> $allowed   Scopes the client is allowed to request
     * @return array<string> The validated scopes (uses defaults if none requested)
     */
    public function validate(array $requested, array $allowed): array
    {
        if ($requested === []) {
            $defaults = $this->defaultScopes;
            if ($defaults === []) {
                return $allowed;
            }

            return array_values(array_intersect($defaults, $allowed));
        }

        foreach ($requested as $scope) {
            if (!$this->has($scope)) {
                throw new \InvalidArgumentException("Unknown scope: {$scope}");
            }

            if (!in_array($scope, $allowed, true)) {
                throw new \InvalidArgumentException("Scope not allowed for this client: {$scope}");
            }
        }

        return $requested;
    }
}
