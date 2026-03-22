<?php

declare(strict_types=1);

namespace Lattice\Observability\Audit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Observability\Audit\Attributes\AuditAction;
use ReflectionMethod;

/**
 * Intercepts controller method calls decorated with #[AuditAction].
 *
 * Captures who (user), what (action description), when (timestamp),
 * and where (URL, method). Runs after the controller method completes.
 */
final class AuditInterceptor implements InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        // Execute the handler first
        $result = $next($context);

        // Check for #[AuditAction] on the controller method
        $attribute = $this->resolveAttribute($context);

        if ($attribute === null) {
            return $result;
        }

        $this->recordAudit($context, $attribute);

        return $result;
    }

    private function resolveAttribute(ExecutionContextInterface $context): ?AuditAction
    {
        $class = $context->getClass();
        $method = $context->getMethod();

        if (!class_exists($class) || !method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        $attributes = $reflection->getAttributes(AuditAction::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function recordAudit(ExecutionContextInterface $context, AuditAction $attribute): void
    {
        $userId = null;
        $principal = $context->getPrincipal();

        if ($principal !== null && method_exists($principal, 'getId')) {
            $userId = (string) $principal->getId();
        }

        $url = null;
        $method = null;
        $ipAddress = null;
        $userAgent = null;

        if (method_exists($context, 'getRequest')) {
            $request = $context->getRequest();

            if (method_exists($request, 'getUri')) {
                $url = $request->getUri();
            }

            if (method_exists($request, 'getMethod')) {
                $method = $request->getMethod();
            }

            if (method_exists($request, 'getHeader')) {
                $ipAddress = $request->getHeader('x-forwarded-for')
                    ?? $request->getHeader('x-real-ip');
                $userAgent = $request->getHeader('user-agent');
            }
        }

        $data = [
            'user_id' => $userId,
            'action' => $attribute->description,
            'auditable_type' => $attribute->category ?? $context->getClass(),
            'auditable_id' => $context->getMethod(),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'url' => $url,
            'method' => $method,
            'created_at' => now(),
        ];

        AuditLog::create($data);
    }
}
