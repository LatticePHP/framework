<?php

declare(strict_types=1);

namespace Lattice\Database;

interface ConnectionInterface
{
    /**
     * Execute a query and return the result set as an array of associative arrays.
     *
     * @param array<mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array;

    /**
     * Execute a statement and return the number of affected rows.
     *
     * @param array<mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int;

    /**
     * Execute a callback within a transaction.
     */
    public function transaction(callable $callback): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function lastInsertId(): string|int;

    public function getDriverName(): string;
}
