<?php

declare(strict_types=1);

namespace Lattice\Authorization;

use Lattice\Auth\Attributes\Authorize;
use Lattice\Auth\Attributes\Roles;
use Lattice\Auth\Attributes\Scopes;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use ReflectionMethod;

final class AuthorizationGuard implements GuardInterface
{
    public function __construct(
        private readonly Gate $gate,
        private readonly PolicyRegistry $policyRegistry,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        $class = $context->getClass();
        $method = $context->getMethod();

        if (!class_exists($class) || !method_exists($class, $method)) {
            return true;
        }

        $reflection = new ReflectionMethod($class, $method);
        $attributes = $this->extractAttributes($reflection);

        // No authorization attributes means no restriction
        if (empty($attributes)) {
            return true;
        }

        $principal = $context->getPrincipal();

        // If attributes are present but no principal, deny
        if ($principal === null) {
            return false;
        }

        // Check #[Authorize('ability')]
        if (isset($attributes['authorize'])) {
            /** @var Authorize $authorize */
            $authorize = $attributes['authorize'];
            if ($authorize->ability !== null) {
                // Try gate first, then policy registry
                if (!$this->gate->allows($principal, $authorize->ability)
                    && !$this->policyRegistry->can($principal, $authorize->ability)) {
                    return false;
                }
            }
        }

        // Check #[Scopes(['scope1', 'scope2'])]
        if (isset($attributes['scopes'])) {
            /** @var Scopes $scopes */
            $scopes = $attributes['scopes'];
            foreach ($scopes->scopes as $scope) {
                if (!$principal->hasScope($scope)) {
                    return false;
                }
            }
        }

        // Check #[Roles(['admin'])]
        if (isset($attributes['roles'])) {
            /** @var Roles $roles */
            $roles = $attributes['roles'];
            foreach ($roles->roles as $role) {
                if (!$principal->hasRole($role)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, object>
     */
    private function extractAttributes(ReflectionMethod $reflection): array
    {
        $result = [];

        $authorizeAttrs = $reflection->getAttributes(Authorize::class);
        if (!empty($authorizeAttrs)) {
            $result['authorize'] = $authorizeAttrs[0]->newInstance();
        }

        $scopesAttrs = $reflection->getAttributes(Scopes::class);
        if (!empty($scopesAttrs)) {
            $result['scopes'] = $scopesAttrs[0]->newInstance();
        }

        $rolesAttrs = $reflection->getAttributes(Roles::class);
        if (!empty($rolesAttrs)) {
            $result['roles'] = $rolesAttrs[0]->newInstance();
        }

        return $result;
    }
}
