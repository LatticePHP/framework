<?php

declare(strict_types=1);

namespace Lattice\JsonApi\Tests\Unit;

use Lattice\JsonApi\JsonApiDeserializer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

final class CreateArticleDto
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {}
}

final class UpdateArticleDto
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $body = null,
    ) {}
}

#[CoversClass(JsonApiDeserializer::class)]
final class JsonApiDeserializerTest extends TestCase
{
    private JsonApiDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new JsonApiDeserializer();
    }

    #[Test]
    public function it_deserializes_json_api_payload_to_dto(): void
    {
        $data = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'Hello World',
                    'body' => 'Content here',
                ],
            ],
        ];

        $dto = $this->deserializer->deserialize($data, CreateArticleDto::class);

        $this->assertInstanceOf(CreateArticleDto::class, $dto);
        $this->assertSame('Hello World', $dto->title);
        $this->assertSame('Content here', $dto->body);
    }

    #[Test]
    public function it_deserializes_partial_update_payload(): void
    {
        $data = [
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => [
                    'title' => 'Updated Title',
                ],
            ],
        ];

        $dto = $this->deserializer->deserialize($data, UpdateArticleDto::class);

        $this->assertInstanceOf(UpdateArticleDto::class, $dto);
        $this->assertSame('Updated Title', $dto->title);
        $this->assertNull($dto->body);
    }

    #[Test]
    public function it_throws_on_missing_data_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "data" key');

        $this->deserializer->deserialize([], CreateArticleDto::class);
    }

    #[Test]
    public function it_throws_on_missing_attributes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "attributes" key');

        $this->deserializer->deserialize([
            'data' => ['type' => 'articles'],
        ], CreateArticleDto::class);
    }

    #[Test]
    public function it_throws_on_invalid_target_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->deserializer->deserialize([
            'data' => [
                'type' => 'articles',
                'attributes' => ['title' => 'Test'],
            ],
        ], 'NonExistent\\ClassName');
    }
}
