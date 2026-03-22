<?php

declare(strict_types=1);

namespace Lattice\Core\Bus\Middleware;

final class TransactionalMiddleware
{
    public function __construct(
        private readonly object $connection,
    ) {}

    public function __invoke(object $command, callable $next): mixed
    {
        if (method_exists($this->connection, 'beginTransaction')) {
            $this->connection->beginTransaction();

            try {
                $result = $next($command);
                $this->connection->commit();

                return $result;
            } catch (\Throwable $e) {
                $this->connection->rollBack();

                throw $e;
            }
        }

        return $next($command);
    }
}
