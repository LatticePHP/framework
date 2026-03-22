<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\Router;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\MatchedRoute;
use Lattice\Routing\ParameterBinding;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testMatchStaticRoute(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $matched = $this->router->match('GET', '/users');

        $this->assertNotNull($matched);
        $this->assertInstanceOf(MatchedRoute::class, $matched);
        $this->assertSame($route, $matched->route);
        $this->assertEmpty($matched->pathParameters);
    }

    public function testMatchRouteWithPathParameter(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users/{id}',
            controllerClass: 'UserController',
            methodName: 'show',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $matched = $this->router->match('GET', '/users/42');

        $this->assertNotNull($matched);
        $this->assertSame($route, $matched->route);
        $this->assertSame(['id' => '42'], $matched->pathParameters);
    }

    public function testMatchRouteWithMultiplePathParameters(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users/{userId}/posts/{postId}',
            controllerClass: 'PostController',
            methodName: 'show',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $matched = $this->router->match('GET', '/users/5/posts/99');

        $this->assertNotNull($matched);
        $this->assertSame(['userId' => '5', 'postId' => '99'], $matched->pathParameters);
    }

    public function testReturnsNullOnNoMatch(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $this->assertNull($this->router->match('GET', '/products'));
    }

    public function testReturnsNullOnMethodMismatch(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $this->assertNull($this->router->match('POST', '/users'));
    }

    public function testMatchesCorrectMethodAmongSamePaths(): void
    {
        $getRoute = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $postRoute = new RouteDefinition(
            httpMethod: 'POST',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'create',
            parameterBindings: [],
        );
        $this->router->addRoute($getRoute);
        $this->router->addRoute($postRoute);

        $matched = $this->router->match('POST', '/users');

        $this->assertNotNull($matched);
        $this->assertSame($postRoute, $matched->route);
    }

    public function testMatchIgnoresTrailingSlash(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $matched = $this->router->match('GET', '/users/');
        $this->assertNotNull($matched);
    }

    public function testMatchStripsQueryString(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $matched = $this->router->match('GET', '/users?page=1');
        $this->assertNotNull($matched);
    }

    public function testMatchIsCaseInsensitiveForMethod(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
        );
        $this->router->addRoute($route);

        $matched = $this->router->match('get', '/users');
        $this->assertNotNull($matched);
    }
}
