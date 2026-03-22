<?php

declare(strict_types=1);

namespace Lattice\Database;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Database\Attributes\Transactional;

final class TransactionalInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly string $connectionName = 'default',
    ) {}

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $transactional = $this->getTransactionalAttribute($context);
        $connName = $transactional?->connection ?? $this->connectionName;

        $connection = $this->connectionManager->connection($connName);

        $connection->beginTransaction();

        try {
            $result = $next();
            $connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function getTransactionalAttribute(ExecutionContextInterface $context): ?Transactional
    {
        try {
            $reflection = new \ReflectionMethod($context->getClass(), $context->getMethod());
            $attributes = $reflection->getAttributes(Transactional::class);

            if (empty($attributes)) {
                return null;
            }

            return $attributes[0]->newInstance();
        } catch (\ReflectionException) {
            return null;
        }
    }
}
