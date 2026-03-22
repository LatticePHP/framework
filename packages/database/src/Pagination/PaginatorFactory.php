<?php

declare(strict_types=1);

namespace Lattice\Database\Pagination;

use Lattice\Database\Query\QueryBuilder;

final class PaginatorFactory
{
    /**
     * Create a paginator from a query builder instance.
     */
    public function fromQueryBuilder(QueryBuilder $query, int $perPage = 15, int $page = 1): Paginator
    {
        $total = $query->count();

        $offset = ($page - 1) * $perPage;

        $items = $query
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return new Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
