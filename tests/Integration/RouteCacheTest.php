<?php

declare(strict_types=1);

namespace Tests\Integration;

use Lattice\Routing\RouteCollector;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use Tests\Integration\Fixtures\UserController;

final class RouteCacheTest extends TestCase
{
    private RouteCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new RouteCollector();
    }

    public function test_collects_routes_from_controller_attributes(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $this->assertNotEmpty($routes);

        $methods = array_map(
            fn (RouteDefinition $r) => $r->httpMethod . ' ' . $r->path,
            $routes,
        );

        $this->assertContains('GET /users', $methods);
        $this->assertContains('GET /users/{id}', $methods);
        $this->assertContains('POST /users', $methods);
        $this->assertContains('DELETE /users/{id}', $methods);
    }

    public function test_caches_routes_to_file_and_restores(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $cacheFile = $this->tempDir . '/routes.cache.php';
        $serialized = serialize($routes);
        file_put_contents($cacheFile, $serialized);

        $this->assertFileExists($cacheFile);

        /** @var RouteDefinition[] $restored */
        $restored = unserialize(file_get_contents($cacheFile));

        $this->assertCount(count($routes), $restored);

        foreach ($routes as $i => $original) {
            $cached = $restored[$i];

            $this->assertSame($original->httpMethod, $cached->httpMethod);
            $this->assertSame($original->path, $cached->path);
            $this->assertSame($original->controllerClass, $cached->controllerClass);
            $this->assertSame($original->methodName, $cached->methodName);
        }
    }

    public function test_router_matches_from_cached_routes(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $cacheFile = $this->tempDir . '/routes.cache.php';
        file_put_contents($cacheFile, serialize($routes));

        /** @var RouteDefinition[] $cachedRoutes */
        $cachedRoutes = unserialize(file_get_contents($cacheFile));

        $router = new Router();
        foreach ($cachedRoutes as $route) {
            $router->addRoute($route);
        }

        $match = $router->match('GET', '/users');
        $this->assertNotNull($match);
        $this->assertSame('GET', $match->route->httpMethod);
        $this->assertSame('/users', $match->route->path);
        $this->assertSame('index', $match->route->methodName);
    }

    public function test_cached_routes_match_parameterized_paths(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $cacheFile = $this->tempDir . '/routes.cache.php';
        file_put_contents($cacheFile, serialize($routes));

        /** @var RouteDefinition[] $cachedRoutes */
        $cachedRoutes = unserialize(file_get_contents($cacheFile));

        $router = new Router();
        foreach ($cachedRoutes as $route) {
            $router->addRoute($route);
        }

        $match = $router->match('GET', '/users/42');
        $this->assertNotNull($match);
        $this->assertSame('show', $match->route->methodName);
        $this->assertSame('42', $match->pathParameters['id']);
    }

    public function test_cached_routes_preserve_controller_class(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $cacheFile = $this->tempDir . '/routes.cache.php';
        file_put_contents($cacheFile, serialize($routes));

        /** @var RouteDefinition[] $cachedRoutes */
        $cachedRoutes = unserialize(file_get_contents($cacheFile));

        foreach ($cachedRoutes as $route) {
            $this->assertSame(UserController::class, $route->controllerClass);
        }
    }

    public function test_clearing_cache_removes_file(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $cacheFile = $this->tempDir . '/routes.cache.php';
        file_put_contents($cacheFile, serialize($routes));
        $this->assertFileExists($cacheFile);

        unlink($cacheFile);
        $this->assertFileDoesNotExist($cacheFile);
    }

    public function test_router_returns_null_for_unmatched_routes(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $router = new Router();
        foreach ($routes as $route) {
            $router->addRoute($route);
        }

        $match = $router->match('GET', '/nonexistent');
        $this->assertNull($match);
    }

    public function test_router_distinguishes_http_methods(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $router = new Router();
        foreach ($routes as $route) {
            $router->addRoute($route);
        }

        $getMatch = $router->match('GET', '/users');
        $this->assertNotNull($getMatch);
        $this->assertSame('index', $getMatch->route->methodName);

        $postMatch = $router->match('POST', '/users');
        $this->assertNotNull($postMatch);
        $this->assertSame('store', $postMatch->route->methodName);
    }

    public function test_static_routes_take_priority_over_parameterized(): void
    {
        $routes = $this->collector->collectFromClass(UserController::class);

        $router = new Router();
        foreach ($routes as $route) {
            $router->addRoute($route);
        }

        // GET /users should match the static index route, not /{id}
        $match = $router->match('GET', '/users');
        $this->assertNotNull($match);
        $this->assertSame('index', $match->route->methodName);
        $this->assertEmpty($match->pathParameters);
    }
}
