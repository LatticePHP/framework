<?php

declare(strict_types=1);

namespace Lattice\Grpc\Tests\Unit;

use Lattice\Grpc\GrpcMethod;
use Lattice\Grpc\GrpcRequest;
use Lattice\Grpc\GrpcResponse;
use Lattice\Grpc\GrpcRouter;
use Lattice\Grpc\GrpcServiceInterface;
use Lattice\Grpc\Testing\InMemoryGrpcTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrpcRequestResponseTest extends TestCase
{
    #[Test]
    public function requestHoldsAllData(): void
    {
        $request = new GrpcRequest(
            serviceName: 'test.Greeter',
            methodName: 'SayHello',
            payload: ['name' => 'Alice'],
            metadata: ['authorization' => 'Bearer token123'],
        );

        $this->assertSame('test.Greeter', $request->serviceName);
        $this->assertSame('SayHello', $request->methodName);
        $this->assertSame(['name' => 'Alice'], $request->payload);
        $this->assertSame('Bearer token123', $request->getMetadataValue('authorization'));
        $this->assertNull($request->getMetadataValue('nonexistent'));
    }

    #[Test]
    public function requestDefaultsToEmptyMetadata(): void
    {
        $request = new GrpcRequest(
            serviceName: 'svc',
            methodName: 'method',
            payload: null,
        );

        $this->assertSame([], $request->metadata);
    }

    #[Test]
    public function responseWithDefaults(): void
    {
        $response = new GrpcResponse(payload: ['greeting' => 'Hello']);

        $this->assertSame(['greeting' => 'Hello'], $response->payload);
        $this->assertSame(0, $response->statusCode);
        $this->assertSame('OK', $response->statusMessage);
        $this->assertSame([], $response->metadata);
        $this->assertTrue($response->isOk());
    }

    #[Test]
    public function responseWithError(): void
    {
        $response = new GrpcResponse(
            payload: null,
            statusCode: 5,
            statusMessage: 'NOT_FOUND',
        );

        $this->assertNull($response->payload);
        $this->assertSame(5, $response->statusCode);
        $this->assertSame('NOT_FOUND', $response->statusMessage);
        $this->assertFalse($response->isOk());
    }

    #[Test]
    public function responseWithMetadata(): void
    {
        $response = new GrpcResponse(
            payload: 'ok',
            metadata: ['x-request-id' => 'abc'],
        );

        $this->assertSame(['x-request-id' => 'abc'], $response->metadata);
    }

    #[Test]
    public function inMemoryTransportSendsRequest(): void
    {
        $router = new GrpcRouter();
        $service = $this->createGreeterService();
        $router->register($service);

        $transport = new InMemoryGrpcTransport($router);

        $request = new GrpcRequest('test.Greeter', 'SayHello', ['name' => 'World']);
        $response = $transport->send($request);

        $this->assertTrue($response->isOk());
        $this->assertSame(['message' => 'Hello World'], $response->payload);
        $this->assertSame(1, $transport->getCallCount());
    }

    #[Test]
    public function inMemoryTransportReturnsErrorForUnknownMethod(): void
    {
        $router = new GrpcRouter();
        $transport = new InMemoryGrpcTransport($router);

        $request = new GrpcRequest('unknown.Service', 'Method', null);
        $response = $transport->send($request);

        $this->assertFalse($response->isOk());
        $this->assertSame(12, $response->statusCode);
        $this->assertStringContainsString('Method not found', $response->statusMessage);
    }

    #[Test]
    public function inMemoryTransportTracksCalls(): void
    {
        $router = new GrpcRouter();
        $router->register($this->createGreeterService());

        $transport = new InMemoryGrpcTransport($router);

        $transport->send(new GrpcRequest('test.Greeter', 'SayHello', ['name' => 'A']));
        $transport->send(new GrpcRequest('test.Greeter', 'SayHello', ['name' => 'B']));

        $this->assertSame(2, $transport->getCallCount());

        $calls = $transport->getCalls();
        $this->assertSame('A', $calls[0]['request']->payload['name']);
        $this->assertSame('B', $calls[1]['request']->payload['name']);
    }

    #[Test]
    public function inMemoryTransportCanBeReset(): void
    {
        $router = new GrpcRouter();
        $router->register($this->createGreeterService());

        $transport = new InMemoryGrpcTransport($router);
        $transport->send(new GrpcRequest('test.Greeter', 'SayHello', ['name' => 'X']));

        $this->assertSame(1, $transport->getCallCount());

        $transport->reset();

        $this->assertSame(0, $transport->getCallCount());
        $this->assertSame([], $transport->getCalls());
    }

    #[Test]
    public function inMemoryTransportHandlesExceptions(): void
    {
        $router = new GrpcRouter();

        $method = new GrpcMethod(
            'test.Broken',
            'Fail',
            'Input',
            'Output',
            fn () => throw new \RuntimeException('Something broke'),
        );

        $service = new class ($method) implements GrpcServiceInterface {
            public function __construct(private readonly GrpcMethod $method) {}
            public function getName(): string { return 'test.Broken'; }
            public function getMethods(): array { return [$this->method]; }
        };

        $router->register($service);

        $transport = new InMemoryGrpcTransport($router);
        $response = $transport->send(new GrpcRequest('test.Broken', 'Fail', null));

        $this->assertFalse($response->isOk());
        $this->assertSame(13, $response->statusCode);
        $this->assertSame('Something broke', $response->statusMessage);
    }

    private function createGreeterService(): GrpcServiceInterface
    {
        $method = new GrpcMethod(
            'test.Greeter',
            'SayHello',
            'HelloRequest',
            'HelloResponse',
            fn (mixed $input) => ['message' => 'Hello ' . $input['name']],
        );

        return new class ($method) implements GrpcServiceInterface {
            public function __construct(private readonly GrpcMethod $method) {}
            public function getName(): string { return 'test.Greeter'; }
            public function getMethods(): array { return [$this->method]; }
        };
    }
}
