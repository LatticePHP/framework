<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Execution;

/**
 * A GraphQL-safe exception whose message can be exposed to clients.
 */
final class GraphqlException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        string $message,
        public readonly array $extensions = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
