<?php

declare(strict_types=1);

namespace Lattice\Loom\Events;

final readonly class JobProcessed
{
    public function __construct(
        public string $jobId,
        public string $className,
        public string $queue,
        public float $runtimeMs,
        public \DateTimeImmutable $timestamp,
    ) {}

    /**
     * @return array{job_id: string, class: string, queue: string, runtime_ms: float, timestamp: string}
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'class' => $this->className,
            'queue' => $this->queue,
            'runtime_ms' => $this->runtimeMs,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
