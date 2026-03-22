<?php

declare(strict_types=1);

namespace Lattice\Authorization;

use Lattice\Auth\Attributes\Policy;
use Lattice\Contracts\Auth\PolicyInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use ReflectionClass;

final class PolicyRegistry
{
    /** @var array<string, PolicyInterface> */
    private array $policies = [];

    /** @var array<class-string, class-string<PolicyInterface>> */
    private array $modelPolicyMap = [];

    public function register(string $resource, PolicyInterface $policy): void
    {
        $this->policies[$resource] = $policy;
    }

    /**
     * Register a policy class for a model class (discovered via #[Policy] attribute).
     *
     * @param class-string<PolicyInterface> $policyClass
     */
    public function registerForModel(string $modelClass, PolicyInterface $policy): void
    {
        $this->modelPolicyMap[$modelClass] = $policy::class;
        // Also register by short name for dot-notation lookup
        $shortName = strtolower((new ReflectionClass($modelClass))->getShortName());
        $this->policies[$shortName] = $policy;
    }

    /**
     * Auto-discover policies from a list of class names.
     * Looks for the #[Policy(ModelClass::class)] attribute on each class.
     *
     * @param list<class-string> $policyClasses
     * @param callable(class-string): PolicyInterface $resolver
     */
    public function discoverPolicies(array $policyClasses, callable $resolver): void
    {
        foreach ($policyClasses as $policyClass) {
            if (!class_exists($policyClass)) {
                continue;
            }

            $reflection = new ReflectionClass($policyClass);
            $attributes = $reflection->getAttributes(Policy::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var Policy $policyAttr */
            $policyAttr = $attributes[0]->newInstance();
            $policy = $resolver($policyClass);

            $this->registerForModel($policyAttr->model, $policy);
        }
    }

    /**
     * Get the policy for a given model class.
     */
    public function getPolicyFor(string $modelClass): ?PolicyInterface
    {
        if (isset($this->modelPolicyMap[$modelClass])) {
            $shortName = strtolower((new ReflectionClass($modelClass))->getShortName());
            return $this->policies[$shortName] ?? null;
        }

        return null;
    }

    /**
     * Check if principal can perform ability.
     * Ability format: "resource.action" (e.g., "posts.view").
     */
    public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
    {
        $dotPos = strpos($ability, '.');

        if ($dotPos === false) {
            return false;
        }

        $resource = substr($ability, 0, $dotPos);
        $action = substr($ability, $dotPos + 1);

        if (!isset($this->policies[$resource])) {
            return false;
        }

        return $this->policies[$resource]->can($principal, $action, $subject);
    }
}
