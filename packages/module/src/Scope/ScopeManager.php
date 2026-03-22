<?php

declare(strict_types=1);

namespace Lattice\Module\Scope;

use Lattice\Contracts\Container\ContainerInterface;

final class ScopeManager
{
    /** @var array<string, ScopeType> */
    private array $scopes = [];

    /**
     * Register the scope for a provider.
     */
    public function register(string $abstract, ScopeType $scope): void
    {
        $this->scopes[$abstract] = $scope;
    }

    /**
     * Get the scope for a provider. Defaults to Transient.
     */
    public function getScopeFor(string $abstract): ScopeType
    {
        return $this->scopes[$abstract] ?? ScopeType::Transient;
    }

    /**
     * Apply the registered scope to the container binding.
     */
    public function applyScope(string $abstract, mixed $concrete, ContainerInterface $container): void
    {
        $scope = $this->getScopeFor($abstract);

        match ($scope) {
            ScopeType::Singleton,
            ScopeType::Request,
            ScopeType::Worker => $container->singleton($abstract, $concrete),
            ScopeType::Transient => $container->bind($abstract, $concrete),
        };
    }

    /**
     * Get all providers registered with a specific scope.
     *
     * @return list<string>
     */
    public function getProvidersByScope(ScopeType $scope): array
    {
        $result = [];

        foreach ($this->scopes as $abstract => $registeredScope) {
            if ($registeredScope === $scope) {
                $result[] = $abstract;
            }
        }

        return $result;
    }

    /**
     * Clear request-scoped providers by resetting the container.
     * In a real application, this would selectively clear only request-scoped bindings.
     */
    public function clearRequestScope(ContainerInterface $container): void
    {
        $container->reset();
    }
}
