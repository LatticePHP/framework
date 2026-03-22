<?php

declare(strict_types=1);

namespace Lattice\RateLimit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\RateLimit\Attributes\RateLimit;
use ReflectionClass;
use ReflectionMethod;

final class RateLimitGuard implements GuardInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        $className = $context->getClass();
        $methodName = $context->getMethod();

        $rateLimit = $this->resolveRateLimit($className, $methodName);

        if ($rateLimit === null) {
            return true;
        }

        $key = $rateLimit->key ?? $this->buildKey($context);
        $result = $this->limiter->attempt($key, $rateLimit->maxAttempts, $rateLimit->decaySeconds);

        return $result->allowed;
    }

    private function resolveRateLimit(string $className, string $methodName): ?RateLimit
    {
        if (!class_exists($className)) {
            return null;
        }

        $classReflection = new ReflectionClass($className);

        // Method-level attribute takes precedence
        if ($classReflection->hasMethod($methodName)) {
            $method = $classReflection->getMethod($methodName);
            $methodAttrs = $method->getAttributes(RateLimit::class);

            if ($methodAttrs !== []) {
                return $methodAttrs[0]->newInstance();
            }
        }

        // Fall back to class-level attribute
        $classAttrs = $classReflection->getAttributes(RateLimit::class);

        if ($classAttrs !== []) {
            return $classAttrs[0]->newInstance();
        }

        return null;
    }

    private function buildKey(ExecutionContextInterface $context): string
    {
        return 'rate_limit:' . $context->getHandler() . ':' . $context->getCorrelationId();
    }
}
