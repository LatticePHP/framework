<?php

declare(strict_types=1);

namespace Lattice\Routing;

final readonly class MatchedRoute
{
    /**
     * @param RouteDefinition $route The matched route definition
     * @param array<string, string> $pathParameters Extracted path parameters
     */
    public function __construct(
        public RouteDefinition $route,
        public array $pathParameters = [],
    ) {}
}
