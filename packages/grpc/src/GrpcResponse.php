<?php

declare(strict_types=1);

namespace Lattice\Grpc;

final class GrpcResponse
{
    /**
     * @param array<string, string|array<string>> $metadata
     */
    public function __construct(
        public readonly mixed $payload,
        public readonly int $statusCode = 0,
        public readonly string $statusMessage = 'OK',
        public readonly array $metadata = [],
    ) {}

    public function isOk(): bool
    {
        return $this->statusCode === 0;
    }
}
