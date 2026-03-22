<?php

declare(strict_types=1);

namespace Lattice\Database\Illuminate;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Adapter that wraps Illuminate\Database\Schema\Builder.
 *
 * Blueprint callbacks receive Illuminate's Blueprint directly,
 * giving access to the full column type set, indexes, foreign keys,
 * and all schema features that Illuminate supports.
 */
final class IlluminateSchemaBuilder
{
    public function __construct(
        private readonly Builder $builder,
    ) {}

    /**
     * Create a new table with the given blueprint callback.
     *
     * The callback receives an Illuminate\Database\Schema\Blueprint instance.
     */
    public function create(string $table, callable $callback): void
    {
        $this->builder->create($table, $callback);
    }

    /**
     * Modify an existing table.
     *
     * The callback receives an Illuminate\Database\Schema\Blueprint instance.
     */
    public function table(string $table, callable $callback): void
    {
        $this->builder->table($table, $callback);
    }

    /**
     * Drop a table.
     */
    public function drop(string $table): void
    {
        $this->builder->drop($table);
    }

    /**
     * Drop a table if it exists.
     */
    public function dropIfExists(string $table): void
    {
        $this->builder->dropIfExists($table);
    }

    /**
     * Rename a table.
     */
    public function rename(string $from, string $to): void
    {
        $this->builder->rename($from, $to);
    }

    /**
     * Check if a table exists.
     */
    public function hasTable(string $table): bool
    {
        return $this->builder->hasTable($table);
    }

    /**
     * Check if a column exists on a table.
     */
    public function hasColumn(string $table, string $column): bool
    {
        return $this->builder->hasColumn($table, $column);
    }

    /**
     * Get the column listing for a table.
     *
     * @return array<int, string>
     */
    public function getColumnListing(string $table): array
    {
        return $this->builder->getColumnListing($table);
    }

    /**
     * Drop all columns from a table.
     *
     * @param array<int, string> $columns
     */
    public function dropColumns(string $table, array $columns): void
    {
        $this->builder->dropColumns($table, $columns);
    }

    /**
     * Access the underlying Illuminate schema builder.
     */
    public function getIlluminateBuilder(): Builder
    {
        return $this->builder;
    }
}
