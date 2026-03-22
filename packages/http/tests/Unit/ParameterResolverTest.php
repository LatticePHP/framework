<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\Exception\UnauthorizedException;
use Lattice\Http\ParameterResolver;
use Lattice\Http\Request;
use Lattice\Routing\ParameterBinding;
use Lattice\Routing\RouteDefinition;
use PHPUnit\Framework\TestCase;

final class ParameterResolverTest extends TestCase
{
    private ParameterResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ParameterResolver();
    }

    public function testResolveBodyParameter(): void
    {
        $request = new Request('POST', '/users', [], [], ['name' => 'John']);
        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'create',
            parameterBindings: [
                new ParameterBinding(type: 'body', parameterName: 'data', name: null),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame(['name' => 'John'], $params['data']);
    }

    public function testResolveQueryParameter(): void
    {
        $request = new Request('GET', '/users', [], ['limit' => '10'], null);
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(type: 'query', parameterName: 'limit', name: 'limit'),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('10', $params['limit']);
    }

    public function testResolveQueryParameterUsesParamNameAsFallback(): void
    {
        $request = new Request('GET', '/users', [], ['page' => '2'], null);
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(type: 'query', parameterName: 'page', name: null),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('2', $params['page']);
    }

    public function testResolvePathParameter(): void
    {
        $request = new Request('GET', '/users/42', [], [], null, ['id' => '42']);
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users/{id}',
            controllerClass: 'UserController',
            methodName: 'show',
            parameterBindings: [
                new ParameterBinding(type: 'param', parameterName: 'id', name: 'id'),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('42', $params['id']);
    }

    public function testResolveHeaderParameter(): void
    {
        $request = new Request('GET', '/users', ['X-Request-Id' => 'abc-123'], [], null);
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(type: 'header', parameterName: 'requestId', name: 'X-Request-Id'),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('abc-123', $params['requestId']);
    }

    public function testResolveCurrentUserParameter(): void
    {
        $principal = $this->createMock(PrincipalInterface::class);
        $request = new Request('GET', '/users', [], [], null);
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(type: 'current_user', parameterName: 'user', name: null),
            ],
        );

        $params = $this->resolver->resolve($request, $route, $principal);

        $this->assertSame($principal, $params['user']);
    }

    public function testResolveMultipleParameters(): void
    {
        $principal = $this->createMock(PrincipalInterface::class);
        $request = new Request(
            'PUT',
            '/users/42',
            ['X-Request-Id' => 'req-1'],
            [],
            ['name' => 'Updated'],
            ['id' => '42'],
        );
        $route = new RouteDefinition(
            httpMethod: 'PUT',
            path: '/users/{id}',
            controllerClass: 'UserController',
            methodName: 'update',
            parameterBindings: [
                new ParameterBinding(type: 'param', parameterName: 'id', name: 'id'),
                new ParameterBinding(type: 'body', parameterName: 'data', name: null),
                new ParameterBinding(type: 'header', parameterName: 'requestId', name: 'X-Request-Id'),
                new ParameterBinding(type: 'current_user', parameterName: 'user', name: null),
            ],
        );

        $params = $this->resolver->resolve($request, $route, $principal);

        $this->assertSame('42', $params['id']);
        $this->assertSame(['name' => 'Updated'], $params['data']);
        $this->assertSame('req-1', $params['requestId']);
        $this->assertSame($principal, $params['user']);
    }

    public function testResolveCurrentUserThrowsWhenNoPrincipal(): void
    {
        $this->expectException(UnauthorizedException::class);

        $request = new Request('GET', '/users', [], [], null);
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(type: 'current_user', parameterName: 'user', name: null),
            ],
        );

        $this->resolver->resolve($request, $route, null);
    }
}
