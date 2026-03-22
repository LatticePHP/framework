<?php

declare(strict_types=1);

namespace Lattice\Database\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fluent builder for full-text-like search queries.
 *
 * Provides a Scout-like API that compiles down to LIKE queries
 * against the model's declared searchable columns.
 */
final class SearchBuilder
{
    /** @var array<string, mixed> */
    private array $wheres = [];

    private ?string $orderByColumn = null;

    private string $orderByDirection = 'asc';

    private ?int $limit = null;

    /**
     * @param class-string $model The fully-qualified model class name
     * @param string $query The search term
     */
    public function __construct(
        private readonly string $model,
        private readonly string $query,
    ) {}

    /**
     * Add a where clause to constrain results beyond the search term.
     */
    public function where(string $column, mixed $value): self
    {
        $this->wheres[$column] = $value;

        return $this;
    }

    /**
     * Set the ordering for search results.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderByColumn = $column;
        $this->orderByDirection = $direction;

        return $this;
    }

    /**
     * Limit the number of results returned.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Execute the search and return all matching models.
     *
     * @return array<int, mixed>
     */
    public function get(): array
    {
        $builder = $this->buildQuery();

        return $builder->get()->all();
    }

    /**
     * Execute the search and return paginated results.
     */
    public function paginate(int $perPage = 15): mixed
    {
        return $this->buildQuery()->paginate($perPage);
    }

    /**
     * Build the underlying Eloquent query from the search parameters.
     */
    private function buildQuery(): Builder
    {
        $modelClass = $this->model;
        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $modelClass();

        $columns = method_exists($instance, 'getSearchableColumns')
            ? $instance->getSearchableColumns()
            : ['*'];

        /** @var Builder $builder */
        $builder = $modelClass::query();

        // Add LIKE search across searchable columns
        if ($this->query !== '' && $columns !== ['*']) {
            $searchTerm = $this->query;
            $builder->where(function (Builder $q) use ($columns, $searchTerm): void {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'LIKE', '%' . $searchTerm . '%');
                }
            });
        }

        // Apply additional where filters
        foreach ($this->wheres as $col => $val) {
            $builder->where($col, $val);
        }

        // Apply ordering
        if ($this->orderByColumn !== null) {
            $builder->orderBy($this->orderByColumn, $this->orderByDirection);
        }

        // Apply limit
        if ($this->limit !== null) {
            $builder->limit($this->limit);
        }

        return $builder;
    }
}
