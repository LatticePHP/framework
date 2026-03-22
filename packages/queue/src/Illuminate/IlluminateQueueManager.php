<?php

declare(strict_types=1);

namespace Lattice\Queue\Illuminate;

use Illuminate\Queue\QueueManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue;

/**
 * Wraps Illuminate's QueueManager to provide full Laravel queue functionality.
 *
 * This is the recommended production path. It gives you access to all Laravel
 * queue drivers (Redis, SQS, Beanstalk, database) plus batching, rate limiting,
 * and everything else Illuminate provides.
 *
 * The lightweight Lattice\Queue\Dispatcher remains available as a fallback for
 * testing or simple use cases that don't need the full Illuminate stack.
 */
final class IlluminateQueueManager
{
    private QueueManager $manager;

    public function __construct(Container $container, array $config)
    {
        $container['config'] = array_merge($container['config'] ?? [], [
            'queue.default' => $config['default'] ?? 'sync',
            'queue.connections' => $config['connections'] ?? [
                'sync' => ['driver' => 'sync'],
                'database' => [
                    'driver' => 'database',
                    'table' => 'jobs',
                    'queue' => 'default',
                    'retry_after' => 90,
                ],
            ],
        ]);

        $this->manager = new QueueManager($container);
    }

    public function push(string|object $job, mixed $data = '', ?string $queue = null): mixed
    {
        return $this->manager->push($job, $data, $queue);
    }

    public function later(\DateTimeInterface|\DateInterval|int $delay, string|object $job, mixed $data = '', ?string $queue = null): mixed
    {
        return $this->manager->later($delay, $job, $data, $queue);
    }

    public function connection(?string $name = null): Queue
    {
        return $this->manager->connection($name);
    }

    /**
     * Full Illuminate queue access: Redis, SQS, Beanstalk, batching, rate limiting, etc.
     */
    public function getManager(): QueueManager
    {
        return $this->manager;
    }
}
