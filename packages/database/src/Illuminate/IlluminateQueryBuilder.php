<?php

declare(strict_types=1);

namespace Lattice\Database\Illuminate;

use Illuminate\Database\Query\Builder;

/**
 * Adapter that wraps Illuminate\Database\Query\Builder and presents
 * a fluent API compatible with the Lattice QueryBuilder interface.
 *
 * This gives users the FULL power of Illuminate's query builder
 * (joins, unions, subqueries, aggregates, raw expressions, etc.)
 * while providing a familiar Lattice-style surface.
 *
 * For advanced usage, call ->getIlluminateBuilder() to access the
 * underlying Illuminate builder directly.
 */
final class IlluminateQueryBuilder
{
    public function __construct(
        private readonly Builder $builder,
    ) {}

    /**
     * Set the table for this query.
     */
    public function table(string $table): self
    {
        return new self($this->builder->from($table));
    }

    /**
     * Set the columns to select.
     */
    public function select(string ...$columns): self
    {
        return new self($this->builder->select($columns));
    }

    /**
     * Add a where clause.
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null && $operator !== null) {
            // Two-argument form: where('name', 'Bob') => where name = 'Bob'
            return new self($this->builder->where($column, '=', $operator));
        }

        return new self($this->builder->where($column, $operator, $value));
    }

    /**
     * Add a where-in clause.
     *
     * @param array<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        return new self($this->builder->whereIn($column, $values));
    }

    /**
     * Add a where-null clause.
     */
    public function whereNull(string $column): self
    {
        return new self($this->builder->whereNull($column));
    }

    /**
     * Add a where-not-null clause.
     */
    public function whereNotNull(string $column): self
    {
        return new self($this->builder->whereNotNull($column));
    }

    /**
     * Add an order-by clause.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        return new self($this->builder->orderBy($column, $direction));
    }

    /**
     * Set the limit.
     */
    public function limit(int $limit): self
    {
        return new self($this->builder->limit($limit));
    }

    /**
     * Set the offset.
     */
    public function offset(int $offset): self
    {
        return new self($this->builder->offset($offset));
    }

    /**
     * Add a join clause.
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return new self($this->builder->join($table, $first, $operator, $second));
    }

    /**
     * Add a left join clause.
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return new self($this->builder->leftJoin($table, $first, $operator, $second));
    }

    /**
     * Add a group-by clause.
     */
    public function groupBy(string ...$columns): self
    {
        return new self($this->builder->groupBy(...$columns));
    }

    /**
     * Add a having clause.
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        return new self($this->builder->having($column, $operator, $value));
    }

    /**
     * Execute the query and return all results as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        return $this->builder->get()->map(fn(object $row) => (array) $row)->all();
    }

    /**
     * Execute the query and return the first result as an array.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $result = $this->builder->first();

        return $result !== null ? (array) $result : null;
    }

    /**
     * Insert a row and return true on success.
     *
     * @param array<string, mixed> $values
     */
    public function insert(array $values): bool
    {
        return $this->builder->insert($values);
    }

    /**
     * Insert a row and return the auto-incrementing ID.
     *
     * @param array<string, mixed> $values
     */
    public function insertGetId(array $values): int
    {
        return (int) $this->builder->insertGetId($values);
    }

    /**
     * Update rows matching the current where clauses.
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        return $this->builder->update($values);
    }

    /**
     * Delete rows matching the current where clauses.
     */
    public function delete(): int
    {
        return $this->builder->delete();
    }

    /**
     * Count rows matching the current where clauses.
     */
    public function count(): int
    {
        return $this->builder->count();
    }

    /**
     * Get the maximum value of a column.
     */
    public function max(string $column): mixed
    {
        return $this->builder->max($column);
    }

    /**
     * Get the minimum value of a column.
     */
    public function min(string $column): mixed
    {
        return $this->builder->min($column);
    }

    /**
     * Get the sum of a column.
     */
    public function sum(string $column): mixed
    {
        return $this->builder->sum($column);
    }

    /**
     * Get the average of a column.
     */
    public function avg(string $column): mixed
    {
        return $this->builder->avg($column);
    }

    /**
     * Check if any rows exist matching the current conditions.
     */
    public function exists(): bool
    {
        return $this->builder->exists();
    }

    /**
     * Access the underlying Illuminate query builder for advanced operations.
     */
    public function getIlluminateBuilder(): Builder
    {
        return $this->builder;
    }
}
