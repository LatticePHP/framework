<?php

declare(strict_types=1);

namespace Lattice\Core\Bus\Middleware;

use Psr\Log\LoggerInterface;

final class LoggingMiddleware
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(object $command, callable $next): mixed
    {
        $commandClass = $command::class;

        $this->log('info', "Dispatching {$commandClass}");

        $startTime = microtime(true);

        try {
            $result = $next($command);
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            $this->log('info', "Completed {$commandClass} in {$elapsed}ms");

            return $result;
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            $this->log('error', "Failed {$commandClass} in {$elapsed}ms: {$e->getMessage()}");

            throw $e;
        }
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->{$level}($message);
        }
    }
}
