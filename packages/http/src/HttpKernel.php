<?php

declare(strict_types=1);

namespace Lattice\Http;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Http\Cors\CorsGuard;
use Lattice\Pipeline\Filter\FilterChain;
use Lattice\Pipeline\Guard\GuardChain;
use Lattice\Pipeline\Interceptor\InterceptorChain;
use Lattice\Pipeline\Pipe\PipeChain;
use Lattice\Pipeline\PipelineConfig;
use Lattice\Pipeline\PipelineExecutor;
use Lattice\ProblemDetails\ProblemDetailsFilter;
use Lattice\Routing\Router;

final class HttpKernel
{
    /** @var array<class-string<GuardInterface>> */
    private readonly array $globalGuardClasses;

    /**
     * @param array<class-string<GuardInterface>> $globalGuardClasses
     */
    public function __construct(
        private readonly Router $router,
        private readonly ContainerInterface $container,
        private readonly ParameterResolver $parameterResolver,
        private readonly ExceptionHandler $exceptionHandler,
        array $globalGuardClasses = [],
    ) {
        $this->globalGuardClasses = $globalGuardClasses;
    }

    public function handle(Request $request): Response
    {
        try {
            // Handle CORS preflight before routing
            if ($this->isCorsPreflightRequest($request)) {
                return $this->handleCorsPreflight($request);
            }

            $matched = $this->router->match($request->method, $request->uri);

            if ($matched === null) {
                return Response::error('Not Found', 404);
            }

            $route = $matched->route;

            // Enrich request with path parameters
            $request = $request->withPathParams($matched->pathParameters);

            // Build execution context
            $context = new HttpExecutionContext(
                request: $request,
                module: '',
                controllerClass: $route->controllerClass,
                methodName: $route->methodName,
            );

            // Resolve all pipeline components from class names
            $globalGuards = $this->resolveInstances($this->globalGuardClasses);
            $routeGuards = $this->resolveInstances($route->guards);
            $guards = array_merge($globalGuards, $routeGuards);

            /** @var InterceptorInterface[] $interceptors */
            $interceptors = $this->resolveInstances($route->interceptors);

            /** @var PipeInterface[] $pipes */
            $pipes = $this->resolveInstances($route->pipes);

            /** @var ExceptionFilterInterface[] $filters */
            $filters = $this->resolveInstances($route->filters);

            // Always append ProblemDetailsFilter as the last filter (fallback)
            if ($this->isProblemDetailsFilterAvailable()) {
                $hasProblemDetailsFilter = false;
                foreach ($filters as $filter) {
                    if ($filter instanceof ProblemDetailsFilter) {
                        $hasProblemDetailsFilter = true;
                        break;
                    }
                }
                if (!$hasProblemDetailsFilter) {
                    $filters[] = $this->resolveInstance(ProblemDetailsFilter::class);
                }
            }

            // Build pipeline config with resolved instances
            $config = new PipelineConfig(
                guards: $guards,
                pipes: $pipes,
                interceptors: $interceptors,
                filters: $filters,
            );

            // Build the pipeline executor
            $executor = new PipelineExecutor(
                guardChain: new GuardChain(),
                interceptorChain: new InterceptorChain(),
                pipeChain: new PipeChain(),
                filterChain: new FilterChain(),
            );

            // Define the controller handler
            $handler = function () use ($route, $request, $context): mixed {
                $controller = $this->container->make($route->controllerClass);
                $params = $this->parameterResolver->resolve($request, $route, $context->getPrincipal());
                $result = $controller->{$route->methodName}(...$params);

                return $this->serializeResult($result);
            };

            // Execute the full pipeline
            $result = $executor->execute($context, $handler, $config);

            if ($result instanceof Response) {
                return $result;
            }

            return Response::json($result);
        } catch (\Throwable $e) {
            return $this->exceptionHandler->handle($e);
        }
    }

    /**
     * Determine if the request is a CORS preflight (OPTIONS with Origin header).
     */
    private function isCorsPreflightRequest(Request $request): bool
    {
        return strtoupper($request->method) === 'OPTIONS'
            && $request->getHeader('Origin') !== null;
    }

    /**
     * Handle a CORS preflight request by finding and using the CorsGuard.
     */
    private function handleCorsPreflight(Request $request): Response
    {
        $origin = $request->getHeader('Origin') ?? '';

        // Try to resolve a CorsGuard from global guards or the container
        foreach ($this->globalGuardClasses as $guardClass) {
            if ($guardClass === CorsGuard::class || is_subclass_of($guardClass, CorsGuard::class)) {
                /** @var CorsGuard $corsGuard */
                $corsGuard = $this->resolveInstance($guardClass);
                return $corsGuard->handlePreflight($origin);
            }
        }

        // If CorsGuard is in the container, use it
        if ($this->container->has(CorsGuard::class)) {
            /** @var CorsGuard $corsGuard */
            $corsGuard = $this->container->get(CorsGuard::class);
            return $corsGuard->handlePreflight($origin);
        }

        // Default: return 200 with basic CORS headers
        return new Response(
            statusCode: 200,
            headers: [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Accept',
            ],
        );
    }

    /**
     * Convert a controller return value into an HTTP Response.
     *
     * - Response object -> return as-is
     * - null -> 204 No Content
     * - Resource -> JSON from toArray()
     * - JsonSerializable / array / object -> JSON response
     */
    private function serializeResult(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result === null) {
            return Response::noContent();
        }

        if ($result instanceof Resource) {
            return Response::json($result->toArray());
        }

        if ($result instanceof \JsonSerializable) {
            return Response::json($result->jsonSerialize());
        }

        return Response::json($result);
    }

    /**
     * Resolve an array of class names into instances from the container.
     *
     * @param array<class-string> $classNames
     * @return array<object>
     */
    private function resolveInstances(array $classNames): array
    {
        return array_map(
            fn(string $class): object => $this->resolveInstance($class),
            $classNames,
        );
    }

    /**
     * Resolve a single class name to an instance via the DI container.
     */
    private function resolveInstance(string $class): object
    {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }

        return $this->container->make($class);
    }

    /**
     * Check if ProblemDetailsFilter class is available.
     */
    private function isProblemDetailsFilterAvailable(): bool
    {
        return class_exists(ProblemDetailsFilter::class);
    }
}
