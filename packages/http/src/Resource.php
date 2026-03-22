<?php

declare(strict_types=1);

namespace Lattice\Http;

/**
 * API Resource for serializing models (or any data) to JSON arrays.
 *
 * Subclasses implement toArray() to define the shape of the JSON output.
 *
 * Example:
 *
 *     final class UserResource extends Resource
 *     {
 *         public function toArray(): array
 *         {
 *             return [
 *                 'id' => $this->resource->id,
 *                 'name' => $this->resource->name,
 *                 'email' => $this->resource->email,
 *                 'posts' => $this->whenLoaded('posts', fn($posts) => PostResource::collection($posts)),
 *             ];
 *         }
 *     }
 */
abstract class Resource implements \JsonSerializable
{
    public function __construct(
        protected readonly mixed $resource,
    ) {}

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a resource for a single model/object.
     */
    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * Create a collection of resources from an iterable.
     *
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $resources): array
    {
        $items = [];

        foreach ($resources as $resource) {
            $items[] = (new static($resource))->toArray();
        }

        return $items;
    }

    /**
     * Create a paginated response wrapping items through this resource.
     */
    public static function paginatedCollection(mixed $paginator): Response
    {
        return ResponseFactory::paginated($paginator, static::class);
    }

    /**
     * Conditionally include a value.
     *
     * Returns $value (or its result if callable) when $condition is true,
     * otherwise returns $default.
     */
    protected function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return is_callable($value) ? $value() : $value;
        }

        return $default;
    }

    /**
     * Include a relationship only if it's loaded on the underlying model.
     *
     * Returns null if the relationship is not loaded.
     * If a callback is provided, it receives the related data and returns the transformed value.
     */
    protected function whenLoaded(string $relationship, ?callable $callback = null): mixed
    {
        if (!is_object($this->resource) || !method_exists($this->resource, 'relationLoaded')) {
            return null;
        }

        if (!$this->resource->relationLoaded($relationship)) {
            return null;
        }

        $related = $this->resource->getRelation($relationship);

        return $callback !== null ? $callback($related) : $related;
    }
}
