<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

final class JsonRpcRequest
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly string $method,
        public readonly array $params = [],
        public readonly string|int|null $id = null,
    ) {}

    public function isNotification(): bool
    {
        return $this->id === null;
    }
}
