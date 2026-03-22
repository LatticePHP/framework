<?php

declare(strict_types=1);

namespace Lattice\Grpc\Testing;

use Lattice\Grpc\GrpcRequest;
use Lattice\Grpc\GrpcResponse;
use Lattice\Grpc\GrpcRouter;

final class InMemoryGrpcTransport
{
    /** @var array<array{request: GrpcRequest, response: GrpcResponse}> */
    private array $calls = [];

    public function __construct(
        private readonly GrpcRouter $router,
    ) {}

    public function send(GrpcRequest $request): GrpcResponse
    {
        $method = $this->router->route($request->serviceName, $request->methodName);

        if ($method === null) {
            $response = new GrpcResponse(
                payload: null,
                statusCode: 12,
                statusMessage: sprintf(
                    'Method not found: %s/%s',
                    $request->serviceName,
                    $request->methodName,
                ),
            );

            $this->calls[] = ['request' => $request, 'response' => $response];

            return $response;
        }

        try {
            $result = $method->invoke($request->payload);
            $response = new GrpcResponse(payload: $result);
        } catch (\Throwable $e) {
            $response = new GrpcResponse(
                payload: null,
                statusCode: 13,
                statusMessage: $e->getMessage(),
            );
        }

        $this->calls[] = ['request' => $request, 'response' => $response];

        return $response;
    }

    /** @return array<array{request: GrpcRequest, response: GrpcResponse}> */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
