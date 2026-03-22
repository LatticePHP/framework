<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\RouteCollector;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\ParameterBinding;
use Lattice\Routing\Tests\Fixtures\UserController;
use Lattice\Routing\Tests\Fixtures\ProductController;
use Lattice\Routing\Tests\Fixtures\NoPrefixController;
use PHPUnit\Framework\TestCase;

final class RouteCollectorTest extends TestCase
{
    private RouteCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new RouteCollector();
    }

    public function testCollectsAllRoutesFromController(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $this->assertCount(5, $routes);
        $this->assertContainsOnlyInstancesOf(RouteDefinition::class, $routes);
    }

    public function testCollectsGetRouteWithPrefix(): void
    {
        // UserController has #[Version('v1')] so paths are prefixed with /v1
        $routes = $this->collector->collectFromClass(UserController::class);
        $indexRoute = $this->findRoute($routes, 'GET', '/v1/users');

        $this->assertNotNull($indexRoute);
        $this->assertSame('GET', $indexRoute->httpMethod);
        $this->assertSame('/v1/users', $indexRoute->path);
        $this->assertSame(UserController::class, $indexRoute->controllerClass);
        $this->assertSame('index', $indexRoute->methodName);
    }

    public function testCollectsGetRouteWithPathParam(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);
        $showRoute = $this->findRoute($routes, 'GET', '/v1/users/{id}');

        $this->assertNotNull($showRoute);
        $this->assertSame('GET', $showRoute->httpMethod);
        $this->assertSame('/v1/users/{id}', $showRoute->path);
        $this->assertSame('show', $showRoute->methodName);
    }

    public function testCollectsPostRoute(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);
        $createRoute = $this->findRoute($routes, 'POST', '/v1/users');

        $this->assertNotNull($createRoute);
        $this->assertSame('POST', $createRoute->httpMethod);
        $this->assertSame('create', $createRoute->methodName);
    }

    public function testCollectsPutRoute(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);
        $updateRoute = $this->findRoute($routes, 'PUT', '/v1/users/{id}');

        $this->assertNotNull($updateRoute);
        $this->assertSame('PUT', $updateRoute->httpMethod);
        $this->assertSame('update', $updateRoute->methodName);
    }

    public function testCollectsDeleteRoute(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);
        $deleteRoute = $this->findRoute($routes, 'DELETE', '/v1/users/{id}');

        $this->assertNotNull($deleteRoute);
        $this->assertSame('DELETE', $deleteRoute->httpMethod);
        $this->assertSame('destroy', $deleteRoute->methodName);
    }

    public function testCollectsParameterBindings(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);
        $updateRoute = $this->findRoute($routes, 'PUT', '/v1/users/{id}');

        $this->assertNotNull($updateRoute);
        $this->assertCount(3, $updateRoute->parameterBindings);

        $bindings = $updateRoute->parameterBindings;
        $this->assertSame('param', $bindings[0]->type);
        $this->assertSame('id', $bindings[0]->name);
        $this->assertSame('body', $bindings[1]->type);
        $this->assertSame('header', $bindings[2]->type);
        $this->assertSame('X-Request-Id', $bindings[2]->name);
    }

    public function testCollectsFromControllerWithoutPrefix(): void
    {
        $routes = $this->collector->collectFromClass(NoPrefixController::class);

        $this->assertCount(1, $routes);
        $this->assertSame('/health', $routes[0]->path);
    }

    public function testCollectsPatchRoute(): void
    {
        $routes = $this->collector->collectFromClass(ProductController::class);
        $patchRoute = $this->findRoute($routes, 'PATCH', '/products/{id}');

        $this->assertNotNull($patchRoute);
        $this->assertSame('PATCH', $patchRoute->httpMethod);
        $this->assertSame('patch', $patchRoute->methodName);
    }

    public function testCollectsCurrentUserBinding(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);
        $createRoute = $this->findRoute($routes, 'POST', '/v1/users');

        $this->assertNotNull($createRoute);
        $bindings = $createRoute->parameterBindings;
        $userBinding = null;
        foreach ($bindings as $binding) {
            if ($binding->type === 'current_user') {
                $userBinding = $binding;
                break;
            }
        }
        $this->assertNotNull($userBinding);
        $this->assertSame('user', $userBinding->parameterName);
    }

    /**
     * @param RouteDefinition[] $routes
     */
    private function findRoute(array $routes, string $method, string $path): ?RouteDefinition
    {
        foreach ($routes as $route) {
            if ($route->httpMethod === $method && $route->path === $path) {
                return $route;
            }
        }
        return null;
    }
}
