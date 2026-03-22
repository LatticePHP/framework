<?php

declare(strict_types=1);

namespace Lattice\RoadRunner;

final readonly class RoadRunnerConfig
{
    /**
     * @param int $httpWorkers Number of HTTP workers
     * @param int $grpcWorkers Number of gRPC workers
     * @param int $maxMemory Maximum memory per worker in MB
     * @param list<string> $resetProviders Provider classes to reset between requests
     */
    public function __construct(
        public int $httpWorkers = 4,
        public int $grpcWorkers = 4,
        public int $maxMemory = 128,
        public array $resetProviders = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            httpWorkers: (int) ($data['httpWorkers'] ?? 4),
            grpcWorkers: (int) ($data['grpcWorkers'] ?? 4),
            maxMemory: (int) ($data['maxMemory'] ?? 128),
            resetProviders: (array) ($data['resetProviders'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'httpWorkers' => $this->httpWorkers,
            'grpcWorkers' => $this->grpcWorkers,
            'maxMemory' => $this->maxMemory,
            'resetProviders' => $this->resetProviders,
        ];
    }
}
