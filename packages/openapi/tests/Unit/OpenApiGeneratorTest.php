<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Tests\Unit;

use Lattice\OpenApi\Attributes\ApiOperation;
use Lattice\OpenApi\Attributes\ApiResponse;
use Lattice\OpenApi\OpenApiDocument;
use Lattice\OpenApi\OpenApiGenerator;
use Lattice\OpenApi\Schema\SchemaGenerator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

// Stub DTO for response type
final class PetDto
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}

// Stub DTO for request body
final class CreatePetDto
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}

// Stub controller with attributes
final class PetController
{
    #[ApiOperation(summary: 'List all pets', operationId: 'listPets', tags: ['pets'])]
    #[ApiResponse(status: 200, description: 'A list of pets', type: PetDto::class)]
    public function index(): void {}

    #[ApiOperation(summary: 'Create a pet', operationId: 'createPet', tags: ['pets'])]
    #[ApiResponse(status: 201, description: 'Pet created', type: PetDto::class)]
    public function store(CreatePetDto $body): void {}
}

#[CoversClass(OpenApiGenerator::class)]
final class OpenApiGeneratorTest extends TestCase
{
    private OpenApiGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OpenApiGenerator(
            title: 'Pet Store API',
            version: '1.0.0',
            schemaGenerator: new SchemaGenerator(),
        );
    }

    #[Test]
    public function it_generates_document_from_routes(): void
    {
        $routes = [
            [
                'path' => '/pets',
                'method' => 'get',
                'controller' => PetController::class,
                'action' => 'index',
            ],
        ];

        $doc = $this->generator->generate($routes);

        $this->assertInstanceOf(OpenApiDocument::class, $doc);
        $array = $doc->toArray();
        $this->assertSame('Pet Store API', $array['info']['title']);
        $this->assertArrayHasKey('/pets', $array['paths']);
        $this->assertSame('listPets', $array['paths']['/pets']['get']['operationId']);
        $this->assertSame('List all pets', $array['paths']['/pets']['get']['summary']);
    }

    #[Test]
    public function it_infers_response_schemas(): void
    {
        $routes = [
            [
                'path' => '/pets',
                'method' => 'get',
                'controller' => PetController::class,
                'action' => 'index',
            ],
        ];

        $doc = $this->generator->generate($routes);
        $array = $doc->toArray();

        $response200 = $array['paths']['/pets']['get']['responses'][200];
        $this->assertSame('A list of pets', $response200['description']);
        $this->assertArrayHasKey('content', $response200);
    }

    #[Test]
    public function it_infers_request_body_from_method_parameters(): void
    {
        $routes = [
            [
                'path' => '/pets',
                'method' => 'post',
                'controller' => PetController::class,
                'action' => 'store',
            ],
        ];

        $doc = $this->generator->generate($routes);
        $array = $doc->toArray();

        $this->assertArrayHasKey('requestBody', $array['paths']['/pets']['post']);
    }

    #[Test]
    public function it_includes_tags(): void
    {
        $routes = [
            [
                'path' => '/pets',
                'method' => 'get',
                'controller' => PetController::class,
                'action' => 'index',
            ],
        ];

        $doc = $this->generator->generate($routes);
        $array = $doc->toArray();

        $this->assertSame(['pets'], $array['paths']['/pets']['get']['tags']);
    }

    #[Test]
    public function it_adds_extra_schemas(): void
    {
        $doc = $this->generator->generate([], [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                ],
            ],
        ]);

        $array = $doc->toArray();
        $this->assertArrayHasKey('Error', $array['components']['schemas']);
    }

    #[Test]
    public function it_handles_routes_without_attributes(): void
    {
        $routes = [
            [
                'path' => '/health',
                'method' => 'get',
                'controller' => PetController::class,
                'action' => 'nonExistentMethod',
            ],
        ];

        // Should not throw, just skip or use defaults
        $doc = $this->generator->generate($routes);
        $this->assertInstanceOf(OpenApiDocument::class, $doc);
    }
}
