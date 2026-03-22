<?php

declare(strict_types=1);

namespace Lattice\Http\Cors;

final readonly class CorsConfig
{
    /**
     * @param string[] $allowedOrigins
     * @param string[] $allowedMethods
     * @param string[] $allowedHeaders
     * @param string[] $exposedHeaders
     */
    public function __construct(
        public array $allowedOrigins = ['*'],
        public array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        public array $allowedHeaders = ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
        public array $exposedHeaders = [],
        public int $maxAge = 0,
        public bool $supportsCredentials = false,
    ) {}

    /**
     * Create a default CORS configuration (allow all).
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create from an associative array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            allowedOrigins: $config['allowedOrigins'] ?? ['*'],
            allowedMethods: $config['allowedMethods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowedHeaders: $config['allowedHeaders'] ?? ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
            exposedHeaders: $config['exposedHeaders'] ?? [],
            maxAge: $config['maxAge'] ?? 0,
            supportsCredentials: $config['supportsCredentials'] ?? false,
        );
    }
}
