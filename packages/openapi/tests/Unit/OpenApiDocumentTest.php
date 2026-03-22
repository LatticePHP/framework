<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Tests\Unit;

use Lattice\OpenApi\OpenApiDocument;
use Lattice\OpenApi\Schema\PathItem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OpenApiDocument::class)]
final class OpenApiDocumentTest extends TestCase
{
    #[Test]
    public function it_creates_document_with_info(): void
    {
        $doc = new OpenApiDocument(
            title: 'Pet Store',
            version: '1.0.0',
            description: 'A sample pet store API',
        );

        $array = $doc->toArray();

        $this->assertSame('3.1.0', $array['openapi']);
        $this->assertSame('Pet Store', $array['info']['title']);
        $this->assertSame('1.0.0', $array['info']['version']);
        $this->assertSame('A sample pet store API', $array['info']['description']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');

        $json = $doc->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame('3.1.0', $decoded['openapi']);
        $this->assertSame('API', $decoded['info']['title']);
    }

    #[Test]
    public function it_serializes_to_yaml(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');

        $yaml = $doc->toYaml();

        $this->assertStringContainsString('openapi: 3.1.0', $yaml);
        $this->assertStringContainsString('title: API', $yaml);
    }

    #[Test]
    public function it_includes_paths(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');
        $doc->addPath(new PathItem(
            path: '/pets',
            method: 'get',
            operationId: 'listPets',
            summary: 'List all pets',
            description: 'Returns all pets',
            responses: [
                200 => ['description' => 'A list of pets'],
            ],
        ));

        $array = $doc->toArray();

        $this->assertArrayHasKey('/pets', $array['paths']);
        $this->assertArrayHasKey('get', $array['paths']['/pets']);
        $this->assertSame('listPets', $array['paths']['/pets']['get']['operationId']);
        $this->assertSame('List all pets', $array['paths']['/pets']['get']['summary']);
    }

    #[Test]
    public function it_includes_component_schemas(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');
        $doc->addSchema('Pet', [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ]);

        $array = $doc->toArray();

        $this->assertArrayHasKey('Pet', $array['components']['schemas']);
        $this->assertSame('object', $array['components']['schemas']['Pet']['type']);
    }

    #[Test]
    public function it_omits_empty_sections(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');

        $array = $doc->toArray();

        $this->assertArrayNotHasKey('paths', $array);
        $this->assertArrayNotHasKey('components', $array);
    }

    #[Test]
    public function it_omits_description_when_null(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');

        $array = $doc->toArray();

        $this->assertArrayNotHasKey('description', $array['info']);
    }

    #[Test]
    public function it_supports_multiple_methods_on_same_path(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');
        $doc->addPath(new PathItem(
            path: '/pets',
            method: 'get',
            operationId: 'listPets',
            responses: [200 => ['description' => 'OK']],
        ));
        $doc->addPath(new PathItem(
            path: '/pets',
            method: 'post',
            operationId: 'createPet',
            responses: [201 => ['description' => 'Created']],
        ));

        $array = $doc->toArray();

        $this->assertArrayHasKey('get', $array['paths']['/pets']);
        $this->assertArrayHasKey('post', $array['paths']['/pets']);
    }

    #[Test]
    public function it_includes_security_on_path_items(): void
    {
        $doc = new OpenApiDocument(title: 'API', version: '1.0.0');
        $doc->addPath(new PathItem(
            path: '/pets',
            method: 'get',
            operationId: 'listPets',
            responses: [200 => ['description' => 'OK']],
            security: [['bearerAuth' => []]],
        ));

        $array = $doc->toArray();

        $this->assertSame([['bearerAuth' => []]], $array['paths']['/pets']['get']['security']);
    }
}
