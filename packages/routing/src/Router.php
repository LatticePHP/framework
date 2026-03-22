<?php

declare(strict_types=1);

namespace Lattice\Routing;

final class Router
{
    /** @var RouteDefinition[] */
    private array $routes = [];

    public function addRoute(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * Get all registered routes.
     *
     * @return RouteDefinition[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get a route by its name.
     */
    public function getNamedRoute(string $name): ?RouteDefinition
    {
        foreach ($this->routes as $route) {
            if ($route->name === $name) {
                return $route;
            }
        }

        return null;
    }

    public function match(string $method, string $uri): ?MatchedRoute
    {
        $method = strtoupper($method);

        // Strip query string
        $questionPos = strpos($uri, '?');
        if ($questionPos !== false) {
            $uri = substr($uri, 0, $questionPos);
        }

        // Normalize trailing slash
        $uri = rtrim($uri, '/') ?: '/';

        // Two-pass matching: static routes first, then parameterized routes.
        // This prevents /:id from matching /pipeline, /upcoming, etc.
        $parameterizedMatches = [];

        foreach ($this->routes as $route) {
            if ($route->httpMethod !== $method) {
                continue;
            }

            $isParameterized = str_contains($route->path, ':') || str_contains($route->path, '{');

            $params = $this->matchPath($route->path, $uri);
            if ($params !== null) {
                if (!$isParameterized) {
                    // Static route match — return immediately (highest priority)
                    return new MatchedRoute($route, $params);
                }
                // Defer parameterized matches
                $parameterizedMatches[] = new MatchedRoute($route, $params);
            }
        }

        // Return the first parameterized match if no static match was found
        return $parameterizedMatches[0] ?? null;
    }

    /**
     * @return array<string, string>|null Path parameters if matched, null otherwise
     */
    private function matchPath(string $routePath, string $uri): ?array
    {
        $routePath = rtrim($routePath, '/') ?: '/';

        // Convert :param style to {param} style before regex conversion
        $routePath = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '{$1}', $routePath);

        // Convert route path to regex
        $pattern = preg_replace_callback(
            '/\{([^}]+)\}/',
            fn (array $matches) => '(?P<' . $matches[1] . '>[^/]+)',
            $routePath,
        );
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return null;
    }
}
