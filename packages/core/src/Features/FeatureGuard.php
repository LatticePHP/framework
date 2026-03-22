<?php

declare(strict_types=1);

namespace Lattice\Core\Features;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Core\Features\Attributes\RequiresFeature;
use ReflectionMethod;

final class FeatureGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        $class = $context->getClass();
        $method = $context->getMethod();

        if (!class_exists($class) || !method_exists($class, $method)) {
            return true;
        }

        $reflection = new ReflectionMethod($class, $method);

        // Check method-level attribute first
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        if ($attributes === []) {
            // Check class-level attribute
            $classReflection = $reflection->getDeclaringClass();
            $attributes = $classReflection->getAttributes(RequiresFeature::class);
        }

        if ($attributes === []) {
            return true;
        }

        foreach ($attributes as $attribute) {
            $requiresFeature = $attribute->newInstance();

            if (!Feature::active($requiresFeature->feature)) {
                return false;
            }
        }

        return true;
    }
}
