<?php

declare(strict_types=1);

namespace Lattice\JsonApi\Tests\Unit;

use Lattice\JsonApi\JsonApiResource;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonApiResource::class)]
final class JsonApiResourceTest extends TestCase
{
    #[Test]
    public function it_creates_resource_with_attributes(): void
    {
        $resource = new JsonApiResource(
            type: 'articles',
            id: '1',
            attributes: ['title' => 'Hello', 'body' => 'World'],
        );

        $array = $resource->toArray();

        $this->assertSame('articles', $array['type']);
        $this->assertSame('1', $array['id']);
        $this->assertSame('Hello', $array['attributes']['title']);
        $this->assertSame('World', $array['attributes']['body']);
    }

    #[Test]
    public function it_includes_relationships(): void
    {
        $resource = new JsonApiResource(
            type: 'articles',
            id: '1',
            attributes: ['title' => 'Hello'],
            relationships: [
                'author' => [
                    'data' => ['type' => 'people', 'id' => '9'],
                ],
            ],
        );

        $array = $resource->toArray();

        $this->assertSame('people', $array['relationships']['author']['data']['type']);
        $this->assertSame('9', $array['relationships']['author']['data']['id']);
    }

    #[Test]
    public function it_includes_links(): void
    {
        $resource = new JsonApiResource(
            type: 'articles',
            id: '1',
            attributes: [],
            links: ['self' => '/articles/1'],
        );

        $array = $resource->toArray();

        $this->assertSame('/articles/1', $array['links']['self']);
    }

    #[Test]
    public function it_includes_meta(): void
    {
        $resource = new JsonApiResource(
            type: 'articles',
            id: '1',
            attributes: [],
            meta: ['created_at' => '2025-01-01'],
        );

        $array = $resource->toArray();

        $this->assertSame('2025-01-01', $array['meta']['created_at']);
    }

    #[Test]
    public function it_omits_empty_optional_fields(): void
    {
        $resource = new JsonApiResource(
            type: 'articles',
            id: '1',
            attributes: ['title' => 'Test'],
        );

        $array = $resource->toArray();

        $this->assertArrayNotHasKey('relationships', $array);
        $this->assertArrayNotHasKey('links', $array);
        $this->assertArrayNotHasKey('meta', $array);
    }
}
