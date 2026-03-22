<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Integration;

use Lattice\Routing\RouteDefinition;
use Lattice\Routing\RouteDiscoverer;
use Lattice\Routing\Router;
use Lattice\Routing\Tests\Fixtures\GuardedController;
use Lattice\Routing\Tests\Fixtures\NoPrefixController;
use Lattice\Routing\Tests\Fixtures\ProductController;
use Lattice\Routing\Tests\Fixtures\UserController;
use PHPUnit\Framework\TestCase;

final class RouteDiscovererTest extends TestCase
{
    private RouteDiscoverer $discoverer;

    protected function setUp(): void
    {
        $this->discoverer = new RouteDiscoverer();
    }

    public function test_discover_returns_empty_for_no_controllers(): void
    {
        $routes = $this->discoverer->discover([]);

        $this->assertSame([], $routes);
    }

    public function test_discover_skips_nonexistent_classes(): void
    {
        $routes = $this->discoverer->discover(['NonExistent\\FakeController']);

        $this->assertSame([], $routes);
    }

    public function test_discover_finds_all_routes_from_single_controller(): void
    {
        $routes = $this->discoverer->discover([ProductController::class]);

        $this->assertCount(4, $routes);
        $this->assertContainsOnlyInstancesOf(RouteDefinition::class, $routes);
    }

    public function test_discover_finds_routes_from_multiple_controllers(): void
    {
        $routes = $this->discoverer->discover([
            UserController::class,
            ProductController::class,
            NoPrefixController::class,
        ]);

        // UserController: 5 routes, ProductController: 4 routes, NoPrefixController: 1 route
        $this->assertCount(10, $routes);
    }

    public function test_discover_routes_have_correct_paths(): void
    {
        $routes = $this->discoverer->discover([ProductController::class]);

        $paths = array_map(fn (RouteDefinition $r) => $r->path, $routes);

        $this->assertContains('/products', $paths);
        $this->assertContains('/products/{id}', $paths);
    }

    public function test_discover_routes_have_correct_http_methods(): void
    {
        $routes = $this->discoverer->discover([ProductController::class]);

        $methods = array_map(fn (RouteDefinition $r) => $r->httpMethod, $routes);

        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('PATCH', $methods);
    }

    public function test_discover_extracts_parameter_bindings(): void
    {
        $routes = $this->discoverer->discover([UserController::class]);
        $updateRoute = $this->findRoute($routes, 'PUT', '/v1/users/{id}');

        $this->assertNotNull($updateRoute, 'PUT /v1/users/{id} route should exist');
        $this->assertCount(3, $updateRoute->parameterBindings);
    }

    public function test_discover_and_register_populates_router(): void
    {
        $router = new Router();
        $this->discoverer->discoverAndRegister([ProductController::class], $router);

        $allRoutes = $router->getRoutes();
        $this->assertCount(4, $allRoutes);

        // Verify routes are matchable
        $matched = $router->match('GET', '/products');
        $this->assertNotNull($matched);
        $this->assertSame('list', $matched->route->methodName);
    }

    public function test_version_attribute_prepends_to_path(): void
    {
        $routes = $this->discoverer->discover([UserController::class]);

        // UserController has #[Version('v1')] and #[Controller('/users')]
        $indexRoute = $this->findRoute($routes, 'GET', '/v1/users');
        $this->assertNotNull($indexRoute, 'GET /v1/users should exist');
        $this->assertSame('v1', $indexRoute->version);
        $this->assertSame('index', $indexRoute->methodName);

        $showRoute = $this->findRoute($routes, 'GET', '/v1/users/{id}');
        $this->assertNotNull($showRoute, 'GET /v1/users/{id} should exist');
    }

    public function test_version_attribute_on_guarded_controller(): void
    {
        $routes = $this->discoverer->discover([GuardedController::class]);

        $dashRoute = $this->findRoute($routes, 'GET', '/v2/admin');
        $this->assertNotNull($dashRoute, 'GET /v2/admin should exist');
        $this->assertSame('v2', $dashRoute->version);

        $actionRoute = $this->findRoute($routes, 'POST', '/v2/admin/action');
        $this->assertNotNull($actionRoute, 'POST /v2/admin/action should exist');
    }

    public function test_name_attribute_sets_route_name(): void
    {
        $routes = $this->discoverer->discover([GuardedController::class]);

        $dashRoute = $this->findRoute($routes, 'GET', '/v2/admin');
        $this->assertNotNull($dashRoute);
        $this->assertSame('admin.dashboard', $dashRoute->name);

        $actionRoute = $this->findRoute($routes, 'POST', '/v2/admin/action');
        $this->assertNotNull($actionRoute);
        $this->assertSame('admin.action', $actionRoute->name);

        $statsRoute = $this->findRoute($routes, 'GET', '/v2/admin/stats');
        $this->assertNotNull($statsRoute);
        $this->assertNull($statsRoute->name, 'Routes without #[Name] should have null name');
    }

    public function test_named_routes_findable_on_router(): void
    {
        $router = new Router();
        $this->discoverer->discoverAndRegister([GuardedController::class], $router);

        $route = $router->getNamedRoute('admin.dashboard');
        $this->assertNotNull($route);
        $this->assertSame('/v2/admin', $route->path);
        $this->assertSame('GET', $route->httpMethod);

        $this->assertNull($router->getNamedRoute('nonexistent'));
    }

    public function test_class_level_guards_extracted(): void
    {
        $routes = $this->discoverer->discover([GuardedController::class]);

        $dashRoute = $this->findRoute($routes, 'GET', '/v2/admin');
        $this->assertNotNull($dashRoute);

        // Class-level guards: AuthGuard, RoleGuard
        $this->assertContains('App\\Guards\\AuthGuard', $dashRoute->guards);
        $this->assertContains('App\\Guards\\RoleGuard', $dashRoute->guards);
    }

    public function test_method_level_guards_merged_with_class(): void
    {
        $routes = $this->discoverer->discover([GuardedController::class]);

        $actionRoute = $this->findRoute($routes, 'POST', '/v2/admin/action');
        $this->assertNotNull($actionRoute);

        // Class-level guards (AuthGuard, RoleGuard) + method-level (CsrfGuard)
        $this->assertCount(3, $actionRoute->guards);
        $this->assertContains('App\\Guards\\AuthGuard', $actionRoute->guards);
        $this->assertContains('App\\Guards\\RoleGuard', $actionRoute->guards);
        $this->assertContains('App\\Guards\\CsrfGuard', $actionRoute->guards);
    }

    public function test_class_level_interceptors_extracted(): void
    {
        $routes = $this->discoverer->discover([GuardedController::class]);

        $dashRoute = $this->findRoute($routes, 'GET', '/v2/admin');
        $this->assertNotNull($dashRoute);

        $this->assertContains('App\\Interceptors\\LoggingInterceptor', $dashRoute->interceptors);
    }

    public function test_controller_without_pipeline_has_empty_arrays(): void
    {
        $routes = $this->discoverer->discover([ProductController::class]);

        foreach ($routes as $route) {
            $this->assertSame([], $route->guards);
            $this->assertSame([], $route->interceptors);
            $this->assertSame([], $route->pipes);
            $this->assertSame([], $route->filters);
        }
    }

    public function test_controller_without_version_has_no_prefix(): void
    {
        $routes = $this->discoverer->discover([ProductController::class]);

        $listRoute = $this->findRoute($routes, 'GET', '/products');
        $this->assertNotNull($listRoute, 'GET /products should exist without version prefix');
        $this->assertNull($listRoute->version);
    }

    public function test_router_get_routes_returns_all_registered(): void
    {
        $router = new Router();
        $this->discoverer->discoverAndRegister([
            ProductController::class,
            NoPrefixController::class,
        ], $router);

        $allRoutes = $router->getRoutes();
        $this->assertCount(5, $allRoutes); // 4 product + 1 health
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
