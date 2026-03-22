<?php

declare(strict_types=1);

namespace Lattice\Routing;

final readonly class RouteDefinition
{
    /**
     * @param string $httpMethod HTTP method (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
     * @param string $path Full path including controller prefix
     * @param string $controllerClass Fully qualified class name
     * @param string $methodName Controller method name
     * @param ParameterBinding[] $parameterBindings
     * @param string|null $name Optional route name for URL generation
     * @param list<class-string> $guards Guard class names from UseGuards
     * @param list<class-string> $interceptors Interceptor class names from UseInterceptors
     * @param list<class-string> $pipes Pipe class names from UsePipes
     * @param list<class-string> $filters Filter class names from UseFilters
     * @param string|null $version API version from Version attribute
     */
    public function __construct(
        public string $httpMethod,
        public string $path,
        public string $controllerClass,
        public string $methodName,
        public array $parameterBindings,
        public ?string $name = null,
        public array $guards = [],
        public array $interceptors = [],
        public array $pipes = [],
        public array $filters = [],
        public ?string $version = null,
    ) {}
}
