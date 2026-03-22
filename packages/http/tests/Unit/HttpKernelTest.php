<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Http\ExceptionHandler;
use Lattice\Http\HttpKernel;
use Lattice\Http\ParameterResolver;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Http\Tests\Fixtures\SimpleController;
use Lattice\Routing\MatchedRoute;
use Lattice\Routing\ParameterBinding;
use Lattice\Routing\RouteCollector;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use PHPUnit\Framework\TestCase;

final class HttpKernelTest extends TestCase
{
    public function testHandleSuccessfulRequest(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/test',
            controllerClass: SimpleController::class,
            methodName: 'index',
            parameterBindings: [],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new SimpleController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')
            ->willReturnCallback(fn(string $class) => match ($class) {
                SimpleController::class => $controller,
                default => new $class(),
            });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/test', [], [], null);
        $response = $kernel->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['message' => 'hello'], $response->body);
    }

    public function testHandleRequestWithPathParameters(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/items/{id}',
            controllerClass: SimpleController::class,
            methodName: 'show',
            parameterBindings: [
                new ParameterBinding(type: 'param', parameterName: 'id', name: 'id'),
            ],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new SimpleController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')
            ->willReturnCallback(fn(string $class) => match ($class) {
                SimpleController::class => $controller,
                default => new $class(),
            });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/items/42', [], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['id' => '42'], $response->body);
    }

    public function testHandleRequestReturns404WhenNoMatch(): void
    {
        $router = new Router();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/nonexistent', [], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(404, $response->statusCode);
    }

    public function testHandleRequestWithBody(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/items',
            controllerClass: SimpleController::class,
            methodName: 'create',
            parameterBindings: [
                new ParameterBinding(type: 'body', parameterName: 'data', name: null),
            ],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new SimpleController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')
            ->willReturnCallback(fn(string $class) => match ($class) {
                SimpleController::class => $controller,
                default => new $class(),
            });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('POST', '/items', [], [], ['name' => 'Widget']);
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['created' => ['name' => 'Widget']], $response->body);
    }

    public function testHandleExceptionInController(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/error',
            controllerClass: SimpleController::class,
            methodName: 'throwError',
            parameterBindings: [],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new SimpleController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')
            ->willReturnCallback(fn(string $class) => match ($class) {
                SimpleController::class => $controller,
                default => new $class(),
            });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/error', [], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(500, $response->statusCode);
    }

    public function testHandleHttpExceptionInController(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/not-found',
            controllerClass: SimpleController::class,
            methodName: 'notFound',
            parameterBindings: [],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new SimpleController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')
            ->willReturnCallback(fn(string $class) => match ($class) {
                SimpleController::class => $controller,
                default => new $class(),
            });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/not-found', [], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(404, $response->statusCode);
    }

    public function testHandleResponseObject(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/custom-response',
            controllerClass: SimpleController::class,
            methodName: 'customResponse',
            parameterBindings: [],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new SimpleController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')
            ->willReturnCallback(fn(string $class) => match ($class) {
                SimpleController::class => $controller,
                default => new $class(),
            });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/custom-response', [], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(201, $response->statusCode);
    }
}
