<?php

declare(strict_types=1);

namespace Lattice\Database\Search;

/**
 * Provides Scout-like search capabilities to Eloquent models.
 *
 * Usage:
 *   class Contact extends Model {
 *       use Searchable;
 *       protected array $searchable = ['first_name', 'last_name', 'email'];
 *   }
 *
 *   Contact::search('john')->where('status', 'lead')->get();
 */
trait Searchable
{
    /**
     * Begin a search query against the model.
     */
    public static function search(string $query): SearchBuilder
    {
        return new SearchBuilder(static::class, $query);
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return $this->toArray();
    }

    /**
     * Get the columns that should be searched.
     *
     * @return array<string>
     */
    public function getSearchableColumns(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->searchable ?? ['*'];
    }
}
