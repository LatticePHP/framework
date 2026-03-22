<?php

declare(strict_types=1);

namespace Lattice\Grpc\Tests\Unit;

use Lattice\Grpc\GrpcMethod;
use Lattice\Grpc\GrpcRouter;
use Lattice\Grpc\GrpcServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrpcRouterTest extends TestCase
{
    private GrpcRouter $router;

    protected function setUp(): void
    {
        $this->router = new GrpcRouter();
    }

    #[Test]
    public function routeReturnsNullForUnregisteredService(): void
    {
        $this->assertNull($this->router->route('unknown.Service', 'Method'));
    }

    #[Test]
    public function routeReturnsNullForUnregisteredMethod(): void
    {
        $service = $this->createService('test.Greeter', [
            new GrpcMethod('test.Greeter', 'SayHello', 'HelloRequest', 'HelloResponse', fn ($input) => $input),
        ]);

        $this->router->register($service);

        $this->assertNull($this->router->route('test.Greeter', 'NonExistent'));
    }

    #[Test]
    public function registerAndRouteSuccessfully(): void
    {
        $handler = fn (mixed $input) => ['message' => 'Hello ' . $input['name']];

        $method = new GrpcMethod('test.Greeter', 'SayHello', 'HelloRequest', 'HelloResponse', $handler);

        $service = $this->createService('test.Greeter', [$method]);

        $this->router->register($service);

        $resolved = $this->router->route('test.Greeter', 'SayHello');

        $this->assertNotNull($resolved);
        $this->assertSame('test.Greeter', $resolved->serviceName);
        $this->assertSame('SayHello', $resolved->methodName);
        $this->assertSame('HelloRequest', $resolved->inputType);
        $this->assertSame('HelloResponse', $resolved->outputType);
    }

    #[Test]
    public function registeredMethodCanBeInvoked(): void
    {
        $handler = fn (mixed $input) => ['message' => 'Hello ' . $input['name']];

        $method = new GrpcMethod('test.Greeter', 'SayHello', 'HelloRequest', 'HelloResponse', $handler);

        $service = $this->createService('test.Greeter', [$method]);

        $this->router->register($service);

        $resolved = $this->router->route('test.Greeter', 'SayHello');
        $result = $resolved->invoke(['name' => 'World']);

        $this->assertSame(['message' => 'Hello World'], $result);
    }

    #[Test]
    public function registerMultipleServices(): void
    {
        $service1 = $this->createService('svc.One', [
            new GrpcMethod('svc.One', 'MethodA', 'InA', 'OutA', fn () => 'A'),
        ]);

        $service2 = $this->createService('svc.Two', [
            new GrpcMethod('svc.Two', 'MethodB', 'InB', 'OutB', fn () => 'B'),
        ]);

        $this->router->register($service1);
        $this->router->register($service2);

        $this->assertNotNull($this->router->route('svc.One', 'MethodA'));
        $this->assertNotNull($this->router->route('svc.Two', 'MethodB'));
        $this->assertNull($this->router->route('svc.One', 'MethodB'));
    }

    #[Test]
    public function getRegisteredServicesReturnsNames(): void
    {
        $service1 = $this->createService('svc.Alpha', [
            new GrpcMethod('svc.Alpha', 'Do', 'In', 'Out', fn () => null),
        ]);
        $service2 = $this->createService('svc.Beta', [
            new GrpcMethod('svc.Beta', 'Do', 'In', 'Out', fn () => null),
        ]);

        $this->router->register($service1);
        $this->router->register($service2);

        $this->assertSame(['svc.Alpha', 'svc.Beta'], $this->router->getRegisteredServices());
    }

    /**
     * @param array<GrpcMethod> $methods
     */
    private function createService(string $name, array $methods): GrpcServiceInterface
    {
        return new class ($name, $methods) implements GrpcServiceInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $methods,
            ) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function getMethods(): array
            {
                return $this->methods;
            }
        };
    }
}
