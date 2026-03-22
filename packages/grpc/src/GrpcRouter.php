<?php

declare(strict_types=1);

namespace Lattice\Grpc;

final class GrpcRouter
{
    /** @var array<string, array<string, GrpcMethod>> */
    private array $routes = [];

    public function register(GrpcServiceInterface $service): void
    {
        $serviceName = $service->getName();

        foreach ($service->getMethods() as $method) {
            $this->routes[$serviceName][$method->methodName] = $method;
        }
    }

    public function route(string $serviceName, string $methodName): ?GrpcMethod
    {
        return $this->routes[$serviceName][$methodName] ?? null;
    }

    /** @return array<string> */
    public function getRegisteredServices(): array
    {
        return array_keys($this->routes);
    }
}
