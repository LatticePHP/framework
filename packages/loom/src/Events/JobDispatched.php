<?php

declare(strict_types=1);

namespace Lattice\Loom\Events;

final readonly class JobDispatched
{
    /**
     * @param string $jobId
     * @param string $className
     * @param string $queue
     * @param string[] $tags
     * @param \DateTimeImmutable $timestamp
     */
    public function __construct(
        public string $jobId,
        public string $className,
        public string $queue,
        public array $tags,
        public \DateTimeImmutable $timestamp,
    ) {}

    /**
     * @return array{job_id: string, class: string, queue: string, tags: string[], timestamp: string}
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'class' => $this->className,
            'queue' => $this->queue,
            'tags' => $this->tags,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
