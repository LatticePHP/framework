<?php

declare(strict_types=1);

namespace Lattice\Database\Pagination;

final readonly class Paginator
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $perPage,
        private int $currentPage,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage(),
                'has_more_pages' => $this->hasMorePages(),
            ],
        ];
    }
}
