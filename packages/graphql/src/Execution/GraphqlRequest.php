<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Execution;

final readonly class GraphqlRequest
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        public string $query,
        public array $variables = [],
        public ?string $operationName = null,
    ) {}

    /**
     * Create from a parsed JSON body (e.g., from HTTP request).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            query: (string) ($data['query'] ?? ''),
            variables: (array) ($data['variables'] ?? []),
            operationName: isset($data['operationName']) ? (string) $data['operationName'] : null,
        );
    }
}
