<?php

declare(strict_types=1);

namespace Lattice\Loom\Events;

final readonly class JobFailed
{
    /**
     * @param string[] $trace
     */
    public function __construct(
        public string $jobId,
        public string $className,
        public string $queue,
        public string $exceptionClass,
        public string $exceptionMessage,
        public array $trace,
        public int $attemptCount,
        public \DateTimeImmutable $timestamp,
    ) {}

    /**
     * @return array{job_id: string, class: string, queue: string, exception_class: string, exception_message: string, trace: string[], attempt_count: int, timestamp: string}
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'class' => $this->className,
            'queue' => $this->queue,
            'exception_class' => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
            'trace' => $this->trace,
            'attempt_count' => $this->attemptCount,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
