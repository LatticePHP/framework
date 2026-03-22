<?php

declare(strict_types=1);

namespace Lattice\Grpc;

final class GrpcRequest
{
    /**
     * @param array<string, string|array<string>> $metadata
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly string $methodName,
        public readonly mixed $payload,
        public readonly array $metadata = [],
    ) {}

    public function getMetadataValue(string $key): string|array|null
    {
        return $this->metadata[$key] ?? null;
    }
}
