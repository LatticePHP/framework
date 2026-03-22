<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Schema;

final class PathItem
{
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly ?string $operationId = null,
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly array $parameters = [],
        public readonly ?array $requestBody = null,
        public readonly array $responses = [],
        public readonly ?array $security = null,
        public readonly array $tags = [],
    ) {}

    public function toArray(): array
    {
        $operation = [];

        if ($this->operationId !== null) {
            $operation['operationId'] = $this->operationId;
        }

        if ($this->summary !== null) {
            $operation['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $operation['description'] = $this->description;
        }

        if ($this->tags !== []) {
            $operation['tags'] = $this->tags;
        }

        if ($this->parameters !== []) {
            $operation['parameters'] = $this->parameters;
        }

        if ($this->requestBody !== null) {
            $operation['requestBody'] = $this->requestBody;
        }

        if ($this->responses !== []) {
            $operation['responses'] = $this->responses;
        }

        if ($this->security !== null) {
            $operation['security'] = $this->security;
        }

        return $operation;
    }
}
