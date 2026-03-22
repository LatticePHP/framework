<?php

declare(strict_types=1);

namespace Lattice\Database\Filter\Attributes;

use Attribute;

/**
 * Declares filtering, sorting, and search metadata for a model class.
 *
 * Used by the compiler to build filtering manifests and validate query parameters.
 *
 * Usage:
 *   #[Filterable(
 *       allowedFilters: ['status', 'company_id', 'value'],
 *       allowedSorts: ['created_at', 'last_name', 'value'],
 *       defaultSort: '-created_at',
 *       searchable: ['first_name', 'last_name', 'email'],
 *   )]
 *   class Contact extends Model { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Filterable
{
    /**
     * @param array<int, string> $allowedFilters Columns that may be filtered on
     * @param array<int, string> $allowedSorts Columns that may be sorted on
     * @param string $defaultSort Default sort expression (- prefix = DESC)
     * @param array<int, string> $searchable Columns included in full-text search
     */
    public function __construct(
        public readonly array $allowedFilters = [],
        public readonly array $allowedSorts = [],
        public readonly string $defaultSort = '-created_at',
        public readonly array $searchable = [],
    ) {}
}
