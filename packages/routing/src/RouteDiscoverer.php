<?php

declare(strict_types=1);

namespace Lattice\Routing;

/**
 * Discovers routes from module controllers at boot time.
 *
 * Scans an array of controller class names, extracts route definitions
 * using RouteCollector, and returns all discovered RouteDefinitions.
 */
final class RouteDiscoverer
{
    private RouteCollector $collector;

    public function __construct(?RouteCollector $collector = null)
    {
        $this->collector = $collector ?? new RouteCollector();
    }

    /**
     * Discover all routes from an array of controller class names.
     *
     * @param list<class-string> $controllerClasses
     * @return RouteDefinition[]
     */
    public function discover(array $controllerClasses): array
    {
        $routes = [];

        foreach ($controllerClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $classRoutes = $this->collector->collectFromClass($class);

            foreach ($classRoutes as $route) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Discover routes and register them on a Router instance.
     *
     * @param list<class-string> $controllerClasses
     */
    public function discoverAndRegister(array $controllerClasses, Router $router): void
    {
        foreach ($this->discover($controllerClasses) as $route) {
            $router->addRoute($route);
        }
    }
}
