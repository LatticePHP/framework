<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\Request;
use PHPUnit\Framework\TestCase;

final class HttpExecutionContextTest extends TestCase
{
    public function testGetTypeReturnsHttp(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
        );

        $this->assertSame(ExecutionType::Http, $context->getType());
    }

    public function testImplementsExecutionContextInterface(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
        );

        $this->assertInstanceOf(ExecutionContextInterface::class, $context);
    }

    public function testGetModule(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
        );

        $this->assertSame('UserModule', $context->getModule());
    }

    public function testGetHandler(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'App\\Controllers\\UserController',
            methodName: 'index',
        );

        $this->assertSame('App\\Controllers\\UserController::index', $context->getHandler());
    }

    public function testGetClassAndMethod(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
        );

        $this->assertSame('UserController', $context->getClass());
        $this->assertSame('index', $context->getMethod());
    }

    public function testGetCorrelationId(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
        );

        $correlationId = $context->getCorrelationId();
        $this->assertNotEmpty($correlationId);
        $this->assertIsString($correlationId);
    }

    public function testGetPrincipalReturnsNullByDefault(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
        );

        $this->assertNull($context->getPrincipal());
    }

    public function testGetPrincipalReturnsPrincipal(): void
    {
        $request = new Request('GET', '/users', [], [], null);
        $principal = $this->createMock(PrincipalInterface::class);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'index',
            principal: $principal,
        );

        $this->assertSame($principal, $context->getPrincipal());
    }

    public function testGetRequest(): void
    {
        $request = new Request('POST', '/users', [], [], ['name' => 'John']);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'UserModule',
            controllerClass: 'UserController',
            methodName: 'create',
        );

        $this->assertSame($request, $context->getRequest());
    }
}
