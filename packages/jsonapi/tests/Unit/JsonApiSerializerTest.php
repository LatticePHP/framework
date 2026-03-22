<?php

declare(strict_types=1);

namespace Lattice\JsonApi\Tests\Unit;

use Lattice\JsonApi\JsonApiDocument;
use Lattice\JsonApi\JsonApiSerializer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

final class ArticleModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
    ) {}
}

final class UserModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

#[CoversClass(JsonApiSerializer::class)]
final class JsonApiSerializerTest extends TestCase
{
    private JsonApiSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonApiSerializer();
    }

    #[Test]
    public function it_serializes_single_resource(): void
    {
        $article = new ArticleModel(id: 1, title: 'Hello', body: 'World');

        $doc = $this->serializer->serialize($article, 'articles');

        $this->assertInstanceOf(JsonApiDocument::class, $doc);
        $array = $doc->toArray();
        $this->assertSame('articles', $array['data']['type']);
        $this->assertSame('1', $array['data']['id']);
        $this->assertSame('Hello', $array['data']['attributes']['title']);
        $this->assertSame('World', $array['data']['attributes']['body']);
    }

    #[Test]
    public function it_excludes_id_from_attributes(): void
    {
        $article = new ArticleModel(id: 1, title: 'Hello', body: 'World');

        $doc = $this->serializer->serialize($article, 'articles');
        $array = $doc->toArray();

        $this->assertArrayNotHasKey('id', $array['data']['attributes']);
    }

    #[Test]
    public function it_serializes_collection(): void
    {
        $articles = [
            new ArticleModel(id: 1, title: 'First', body: 'Body 1'),
            new ArticleModel(id: 2, title: 'Second', body: 'Body 2'),
        ];

        $doc = $this->serializer->serializeCollection($articles, 'articles');
        $array = $doc->toArray();

        $this->assertCount(2, $array['data']);
        $this->assertSame('1', $array['data'][0]['id']);
        $this->assertSame('2', $array['data'][1]['id']);
    }

    #[Test]
    public function it_serializes_empty_collection(): void
    {
        $doc = $this->serializer->serializeCollection([], 'articles');
        $array = $doc->toArray();

        $this->assertSame([], $array['data']);
    }

    #[Test]
    public function it_handles_object_without_id_property(): void
    {
        $obj = new class {
            public string $name = 'test';
            public string $value = 'data';
        };

        $doc = $this->serializer->serialize($obj, 'items');
        $array = $doc->toArray();

        $this->assertSame('items', $array['data']['type']);
        $this->assertArrayHasKey('name', $array['data']['attributes']);
    }

    #[Test]
    public function it_uses_custom_id_property(): void
    {
        $user = new UserModel(id: 42, name: 'John', email: 'john@example.com');

        $serializer = new JsonApiSerializer(idProperty: 'id');
        $doc = $serializer->serialize($user, 'users');
        $array = $doc->toArray();

        $this->assertSame('42', $array['data']['id']);
        $this->assertSame('John', $array['data']['attributes']['name']);
    }
}
