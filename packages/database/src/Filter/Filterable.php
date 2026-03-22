<?php

declare(strict_types=1);

namespace Lattice\Database\Filter;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides Spatie-style query filtering to Eloquent models.
 *
 * Usage:
 *   class Contact extends Model {
 *       use Filterable;
 *       protected array $allowedFilters = ['status', 'company_id'];
 *       protected array $allowedSorts = ['created_at', 'last_name'];
 *       protected array $searchable = ['first_name', 'last_name', 'email'];
 *   }
 *
 *   Contact::filter(QueryFilter::fromRequest($request->query));
 */
trait Filterable
{
    /**
     * Apply a QueryFilter to the model's query builder.
     */
    public static function filter(QueryFilter $filter): Builder
    {
        return $filter->apply(static::query(), new static());
    }

    /**
     * Get the columns that may be filtered on.
     *
     * @return array<int, string>
     */
    public function getAllowedFilters(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->allowedFilters ?? [];
    }

    /**
     * Get the columns that may be sorted on.
     *
     * @return array<int, string>
     */
    public function getAllowedSorts(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->allowedSorts ?? [];
    }

    /**
     * Get the columns included in full-text search.
     *
     * @return array<int, string>
     */
    public function getSearchableFields(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->searchable ?? [];
    }
}
