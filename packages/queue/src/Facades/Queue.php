<?php

declare(strict_types=1);

namespace Lattice\Queue\Facades;

use Lattice\Queue\Dispatcher;
use Lattice\Queue\JobInterface;

final class Queue
{
    private static ?Dispatcher $instance = null;

    public static function setInstance(Dispatcher $dispatcher): void
    {
        self::$instance = $dispatcher;
    }

    public static function getInstance(): Dispatcher
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Queue facade has not been initialized. Call Queue::setInstance() first.');
        }

        return self::$instance;
    }

    public static function dispatch(JobInterface $job): string
    {
        return self::getInstance()->dispatch($job);
    }

    public static function later(int $delaySeconds, JobInterface $job): string
    {
        return self::getInstance()->dispatchAfter($job, $delaySeconds);
    }

    /**
     * Reset the facade instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
