<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Database\Pagination\Paginator;
use Lattice\Http\Resource;
use Lattice\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResourceTest extends TestCase
{
    #[Test]
    public function test_make_wraps_model(): void
    {
        $model = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
        $resource = UserResource::make($model);

        $this->assertInstanceOf(UserResource::class, $resource);
        $this->assertSame([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ], $resource->toArray());
    }

    #[Test]
    public function test_json_serialize_returns_to_array(): void
    {
        $model = (object) ['id' => 1, 'name' => 'Bob', 'email' => 'bob@example.com'];
        $resource = UserResource::make($model);

        $this->assertSame($resource->toArray(), $resource->jsonSerialize());
    }

    #[Test]
    public function test_collection_wraps_multiple(): void
    {
        $models = [
            (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            (object) ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $result = UserResource::collection($models);

        $this->assertCount(2, $result);
        $this->assertSame([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ], $result);
    }

    #[Test]
    public function test_collection_with_empty_iterable(): void
    {
        $result = UserResource::collection([]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_when_includes_value_if_condition_true(): void
    {
        $model = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'isAdmin' => true];
        $resource = ConditionalResource::make($model);

        $result = $resource->toArray();
        $this->assertSame('admin', $result['role']);
    }

    #[Test]
    public function test_when_excludes_value_if_condition_false(): void
    {
        $model = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'isAdmin' => false];
        $resource = ConditionalResource::make($model);

        $result = $resource->toArray();
        $this->assertNull($result['role']);
    }

    #[Test]
    public function test_when_with_callable_value(): void
    {
        $model = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'isAdmin' => true];
        $resource = ConditionalCallableResource::make($model);

        $result = $resource->toArray();
        $this->assertSame('ADMIN', $result['role']);
    }

    #[Test]
    public function test_when_with_default(): void
    {
        $model = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'isAdmin' => false];
        $resource = ConditionalWithDefaultResource::make($model);

        $result = $resource->toArray();
        $this->assertSame('user', $result['role']);
    }

    #[Test]
    public function test_when_loaded_returns_null_for_non_object(): void
    {
        $resource = WhenLoadedResource::make(['id' => 1]);

        $result = $resource->toArray();
        $this->assertNull($result['posts']);
    }

    #[Test]
    public function test_when_loaded_returns_null_if_relation_not_loaded(): void
    {
        $model = new FakeModel(1, 'Alice');
        $resource = WhenLoadedResource::make($model);

        $result = $resource->toArray();
        $this->assertNull($result['posts']);
    }

    #[Test]
    public function test_when_loaded_returns_relation_if_loaded(): void
    {
        $posts = [['id' => 1, 'title' => 'Hello']];
        $model = new FakeModel(1, 'Alice', ['posts' => $posts]);
        $resource = WhenLoadedResource::make($model);

        $result = $resource->toArray();
        $this->assertSame($posts, $result['posts']);
    }

    #[Test]
    public function test_when_loaded_with_callback(): void
    {
        $posts = [['id' => 1, 'title' => 'Hello']];
        $model = new FakeModel(1, 'Alice', ['posts' => $posts]);
        $resource = WhenLoadedWithCallbackResource::make($model);

        $result = $resource->toArray();
        $this->assertSame(1, $result['post_count']);
    }

    #[Test]
    public function test_paginated_collection(): void
    {
        $paginator = new Paginator(
            items: [
                (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                (object) ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
            ],
            total: 5,
            perPage: 2,
            currentPage: 1,
        );

        $response = UserResource::paginatedCollection($paginator);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        $this->assertSame([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ], $body['data']);

        $this->assertSame(5, $body['meta']['total']);
        $this->assertSame(2, $body['meta']['per_page']);
        $this->assertSame(1, $body['meta']['current_page']);
        $this->assertSame(3, $body['meta']['last_page']);
        $this->assertTrue($body['meta']['has_more']);
    }
}

// --- Test Fixtures ---

final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
        ];
    }
}

final class ConditionalResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'role' => $this->when($this->resource->isAdmin, 'admin'),
        ];
    }
}

final class ConditionalCallableResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'role' => $this->when($this->resource->isAdmin, fn() => 'ADMIN'),
        ];
    }
}

final class ConditionalWithDefaultResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'role' => $this->when($this->resource->isAdmin, 'admin', 'user'),
        ];
    }
}

final class WhenLoadedResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => is_object($this->resource) ? $this->resource->id : $this->resource['id'],
            'posts' => $this->whenLoaded('posts'),
        ];
    }
}

final class WhenLoadedWithCallbackResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'post_count' => $this->whenLoaded('posts', fn($posts) => count($posts)),
        ];
    }
}

/**
 * Fake model that simulates Eloquent's relationLoaded/getRelation.
 */
final class FakeModel
{
    /** @var array<string, mixed> */
    private array $relations;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        array $relations = [],
    ) {
        $this->relations = $relations;
    }

    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }
}
