<?php

declare(strict_types=1);

namespace Lattice\Observability\Middleware;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Observability\Log;

final class RequestLoggingInterceptor implements InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $start = hrtime(true);

        try {
            $result = $next($context);
            $duration = (hrtime(true) - $start) / 1e6; // ms

            $logContext = [
                'handler' => $context->getHandler(),
                'module' => $context->getModule(),
                'correlation_id' => $context->getCorrelationId(),
                'duration_ms' => round($duration, 2),
            ];

            // If the context exposes HTTP request details, include them
            if (method_exists($context, 'getRequest')) {
                $request = $context->getRequest();
                if (method_exists($request, 'getMethod')) {
                    $logContext['method'] = $request->getMethod();
                }
                if (method_exists($request, 'getUri')) {
                    $logContext['path'] = $request->getUri();
                }
            }

            // Detect status code from response
            if (is_object($result) && method_exists($result, 'getStatusCode')) {
                $logContext['status'] = $result->getStatusCode();
            }

            Log::info('Request handled', $logContext);

            return $result;
        } catch (\Throwable $e) {
            $duration = (hrtime(true) - $start) / 1e6;

            $logContext = [
                'handler' => $context->getHandler(),
                'module' => $context->getModule(),
                'correlation_id' => $context->getCorrelationId(),
                'error' => $e->getMessage(),
                'error_class' => $e::class,
                'duration_ms' => round($duration, 2),
            ];

            if (method_exists($context, 'getRequest')) {
                $request = $context->getRequest();
                if (method_exists($request, 'getMethod')) {
                    $logContext['method'] = $request->getMethod();
                }
                if (method_exists($request, 'getUri')) {
                    $logContext['path'] = $request->getUri();
                }
            }

            Log::error('Request failed', $logContext);

            throw $e;
        }
    }
}
