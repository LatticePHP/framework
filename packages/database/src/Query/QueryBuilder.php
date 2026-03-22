<?php

declare(strict_types=1);

namespace Lattice\Database\Query;

use Lattice\Database\ConnectionInterface;

final class QueryBuilder
{
    private string $table = '';

    /** @var string[] */
    private array $columns = ['*'];

    /** @var array<array{column: string, operator: string, value: mixed}> */
    private array $wheres = [];

    /** @var array<array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function table(string $table): self
    {
        $clone = clone $this;
        $clone->table = $table;
        return $clone;
    }

    public function select(string ...$columns): self
    {
        $clone = clone $this;
        $clone->columns = $columns;
        return $clone;
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        $clone = clone $this;

        if ($value === null) {
            // Two-argument form: where('name', 'Bob') means where('name', '=', 'Bob')
            $value = $operator;
            $operator = '=';
        }

        $clone->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        return $clone;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $clone = clone $this;
        $clone->orders[] = ['column' => $column, 'direction' => strtoupper($direction)];
        return $clone;
    }

    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->limitValue = $limit;
        return $clone;
    }

    public function offset(int $offset): self
    {
        $clone = clone $this;
        $clone->offsetValue = $offset;
        return $clone;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->buildSelect();
        return $this->connection->query($sql, $bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Insert a row and return the last insert ID.
     *
     * @param array<string, mixed> $values
     */
    public function insert(array $values): int
    {
        $columns = array_keys($values);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', $columns);

        $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholders})";

        $this->connection->execute($sql, array_values($values));

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update rows matching the where clauses.
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        $setClauses = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

        [$whereClause, $whereBindings] = $this->buildWhereClause();
        if ($whereClause !== '') {
            $sql .= ' ' . $whereClause;
            $bindings = array_merge($bindings, $whereBindings);
        }

        return $this->connection->execute($sql, $bindings);
    }

    /**
     * Delete rows matching the where clauses.
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        $bindings = [];

        [$whereClause, $whereBindings] = $this->buildWhereClause();
        if ($whereClause !== '') {
            $sql .= ' ' . $whereClause;
            $bindings = $whereBindings;
        }

        return $this->connection->execute($sql, $bindings);
    }

    /**
     * Count rows matching the where clauses.
     */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as aggregate'];

        [$sql, $bindings] = $this->buildSelect();
        $this->columns = $originalColumns;

        $result = $this->connection->query($sql, $bindings);

        return (int) ($result[0]['aggregate'] ?? 0);
    }

    /**
     * @return array{string, array<mixed>}
     */
    private function buildSelect(): array
    {
        $columnList = implode(', ', $this->columns);
        $sql = "SELECT {$columnList} FROM {$this->table}";
        $bindings = [];

        [$whereClause, $whereBindings] = $this->buildWhereClause();
        if ($whereClause !== '') {
            $sql .= ' ' . $whereClause;
            $bindings = $whereBindings;
        }

        if (!empty($this->orders)) {
            $orderClauses = array_map(
                fn(array $order) => "{$order['column']} {$order['direction']}",
                $this->orders,
            );
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return [$sql, $bindings];
    }

    /**
     * @return array{string, array<mixed>}
     */
    private function buildWhereClause(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($this->wheres as $where) {
            $clauses[] = "{$where['column']} {$where['operator']} ?";
            $bindings[] = $where['value'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $bindings];
    }
}
