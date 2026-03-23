<?php

declare(strict_types=1);

namespace Lattice\Database\Filter;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies query-string-based filtering, sorting, searching, and eager-loading.
 *
 * Supports Spatie-style query parameters:
 *   ?filter[status]=lead
 *   ?filter[status]=lead,prospect   (whereIn)
 *   ?filter[value][gt]=1000          (range operators: gt, lt, gte, lte, from, to)
 *   ?filter[company_id]=null          (whereNull)
 *   ?sort=-created_at,last_name       (- prefix = DESC)
 *   ?search=john                      (LIKE across searchable columns)
 *   ?include=company,tags             (eager loading)
 *   ?per_page=25&page=2               (pagination)
 */
final class QueryFilter
{
    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var array<string, string> column => 'asc'|'desc' */
    private array $sorts = [];

    private ?string $search = null;

    /** @var array<int, string> */
    private array $includes = [];

    private int $perPage = 15;

    private int $page = 1;

    /** Maximum allowed per_page to prevent DoS via unbounded queries */
    private static int $maxPerPage = 100;

    /**
     * Set the global maximum per_page value.
     */
    public static function setMaxPerPage(int $max): void
    {
        self::$maxPerPage = $max;
    }

    /**
     * Create a QueryFilter from a request query-string array.
     *
     * @param array<string, mixed> $query
     */
    public static function fromRequest(array $query): self
    {
        $instance = new self();
        $instance->filters = $query['filter'] ?? [];
        $instance->search = $query['search'] ?? null;
        $instance->includes = isset($query['include'])
            ? explode(',', (string) $query['include'])
            : [];
        $instance->perPage = min((int) ($query['per_page'] ?? 15), self::$maxPerPage);
        $instance->page = max(1, (int) ($query['page'] ?? 1));

        // Parse sort: -created_at means DESC, created_at means ASC
        if (isset($query['sort'])) {
            foreach (explode(',', (string) $query['sort']) as $sort) {
                if (str_starts_with($sort, '-')) {
                    $instance->sorts[substr($sort, 1)] = 'desc';
                } else {
                    $instance->sorts[$sort] = 'asc';
                }
            }
        }

        return $instance;
    }

    /**
     * Apply all filters, sorts, search, and includes to the given query builder.
     *
     * @param Builder $query The Eloquent query builder
     * @param object $model The model instance (used to read allowed* / searchable properties)
     */
    public function apply(Builder $query, object $model): Builder
    {
        $allowedFilters = method_exists($model, 'getAllowedFilters')
            ? $model->getAllowedFilters()
            : array_keys($this->filters);
        $allowedSorts = method_exists($model, 'getAllowedSorts')
            ? $model->getAllowedSorts()
            : array_keys($this->sorts);
        $searchable = method_exists($model, 'getSearchableFields')
            ? $model->getSearchableFields()
            : [];

        // Apply filters
        foreach ($this->filters as $field => $value) {
            if (!in_array($field, $allowedFilters, true)) {
                continue;
            }

            if (is_array($value)) {
                // Range operators: filter[value][gt]=1000
                if (isset($value['gt'])) {
                    $query->where($field, '>', $value['gt']);
                }
                if (isset($value['lt'])) {
                    $query->where($field, '<', $value['lt']);
                }
                if (isset($value['gte'])) {
                    $query->where($field, '>=', $value['gte']);
                }
                if (isset($value['lte'])) {
                    $query->where($field, '<=', $value['lte']);
                }
                if (isset($value['from'])) {
                    $query->where($field, '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $query->where($field, '<=', $value['to']);
                }
            } elseif (str_contains((string) $value, ',')) {
                // Multiple values: filter[status]=lead,prospect
                $query->whereIn($field, explode(',', (string) $value));
            } elseif ($value === 'null') {
                $query->whereNull($field);
            } else {
                $query->where($field, $value);
            }
        }

        // Apply search across searchable columns
        if ($this->search !== null && $this->search !== '' && !empty($searchable)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($searchable, $search): void {
                foreach ($searchable as $col) {
                    $q->orWhere($col, 'LIKE', '%' . $search . '%');
                }
            });
        }

        // Apply sorts
        foreach ($this->sorts as $field => $direction) {
            if (in_array($field, $allowedSorts, true)) {
                $query->orderBy($field, $direction);
            }
        }

        // Apply eager-loading includes
        if (!empty($this->includes)) {
            $query->with($this->includes);
        }

        return $query;
    }

    /**
     * Get the per-page count for pagination.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the current page number.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Get the parsed filters.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get the parsed sorts.
     *
     * @return array<string, string>
     */
    public function getSorts(): array
    {
        return $this->sorts;
    }

    /**
     * Get the search term.
     */
    public function getSearch(): ?string
    {
        return $this->search;
    }

    /**
     * Get the requested includes.
     *
     * @return array<int, string>
     */
    public function getIncludes(): array
    {
        return $this->includes;
    }
}
