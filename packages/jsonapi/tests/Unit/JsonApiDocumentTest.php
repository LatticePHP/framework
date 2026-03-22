<?php

declare(strict_types=1);

namespace Lattice\JsonApi\Tests\Unit;

use Lattice\JsonApi\JsonApiDocument;
use Lattice\JsonApi\JsonApiResource;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonApiDocument::class)]
final class JsonApiDocumentTest extends TestCase
{
    #[Test]
    public function it_creates_document_with_single_resource(): void
    {
        $resource = new JsonApiResource(
            type: 'articles',
            id: '1',
            attributes: ['title' => 'Hello World'],
        );

        $doc = JsonApiDocument::fromResource($resource);
        $array = $doc->toArray();

        $this->assertSame('articles', $array['data']['type']);
        $this->assertSame('1', $array['data']['id']);
        $this->assertSame('Hello World', $array['data']['attributes']['title']);
    }

    #[Test]
    public function it_creates_document_with_collection(): void
    {
        $resources = [
            new JsonApiResource(type: 'articles', id: '1', attributes: ['title' => 'First']),
            new JsonApiResource(type: 'articles', id: '2', attributes: ['title' => 'Second']),
        ];

        $doc = JsonApiDocument::fromCollection($resources);
        $array = $doc->toArray();

        $this->assertCount(2, $array['data']);
        $this->assertSame('1', $array['data'][0]['id']);
        $this->assertSame('2', $array['data'][1]['id']);
    }

    #[Test]
    public function it_includes_meta(): void
    {
        $doc = JsonApiDocument::fromResource(
            new JsonApiResource(type: 'articles', id: '1', attributes: []),
        );
        $doc->setMeta(['total' => 100]);

        $array = $doc->toArray();

        $this->assertSame(100, $array['meta']['total']);
    }

    #[Test]
    public function it_includes_links(): void
    {
        $doc = JsonApiDocument::fromResource(
            new JsonApiResource(type: 'articles', id: '1', attributes: []),
        );
        $doc->setLinks(['self' => '/articles/1']);

        $array = $doc->toArray();

        $this->assertSame('/articles/1', $array['links']['self']);
    }

    #[Test]
    public function it_includes_sideloaded_relationships(): void
    {
        $doc = JsonApiDocument::fromResource(
            new JsonApiResource(type: 'articles', id: '1', attributes: ['title' => 'Hello']),
        );
        $doc->addIncluded(
            new JsonApiResource(type: 'people', id: '9', attributes: ['name' => 'Author']),
        );

        $array = $doc->toArray();

        $this->assertCount(1, $array['included']);
        $this->assertSame('people', $array['included'][0]['type']);
        $this->assertSame('9', $array['included'][0]['id']);
    }

    #[Test]
    public function it_creates_error_document(): void
    {
        $doc = JsonApiDocument::fromErrors([
            ['status' => '404', 'title' => 'Not Found', 'detail' => 'Resource not found'],
        ]);

        $array = $doc->toArray();

        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayNotHasKey('data', $array);
        $this->assertSame('404', $array['errors'][0]['status']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $doc = JsonApiDocument::fromResource(
            new JsonApiResource(type: 'articles', id: '1', attributes: ['title' => 'Test']),
        );

        $json = $doc->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame('articles', $decoded['data']['type']);
    }

    #[Test]
    public function it_omits_empty_sections(): void
    {
        $doc = JsonApiDocument::fromResource(
            new JsonApiResource(type: 'articles', id: '1', attributes: ['title' => 'Test']),
        );

        $array = $doc->toArray();

        $this->assertArrayNotHasKey('meta', $array);
        $this->assertArrayNotHasKey('links', $array);
        $this->assertArrayNotHasKey('included', $array);
        $this->assertArrayNotHasKey('errors', $array);
    }
}
