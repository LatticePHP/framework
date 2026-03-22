<?php

declare(strict_types=1);

namespace Tests\Integration;

use Lattice\OpenApi\OpenApiGenerator;
use Lattice\OpenApi\Schema\SchemaGenerator;
use Lattice\Routing\RouteCollector;
use Lattice\Routing\RouteDefinition;
use Tests\Integration\Fixtures\UserController;

final class OpenApiTest extends TestCase
{
    private OpenApiGenerator $generator;
    private RouteCollector $routeCollector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new OpenApiGenerator(
            title: 'Test API',
            version: '1.0.0',
            schemaGenerator: new SchemaGenerator(),
            description: 'Integration test API',
        );

        $this->routeCollector = new RouteCollector();
    }

    public function test_generates_valid_openapi_document_structure(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertSame('Test API', $spec['info']['title']);
        $this->assertSame('1.0.0', $spec['info']['version']);
        $this->assertSame('Integration test API', $spec['info']['description']);
    }

    public function test_generates_paths_from_annotated_controller(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('/users', $spec['paths']);
        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
    }

    public function test_generates_correct_http_methods_for_paths(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $this->assertArrayHasKey('get', $spec['paths']['/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/users']);
        $this->assertArrayHasKey('get', $spec['paths']['/users/{id}']);
        $this->assertArrayHasKey('delete', $spec['paths']['/users/{id}']);
    }

    public function test_generates_operation_metadata(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $getUsers = $spec['paths']['/users']['get'];
        $this->assertSame('listUsers', $getUsers['operationId']);
        $this->assertSame('List all users', $getUsers['summary']);
        $this->assertContains('users', $getUsers['tags']);
    }

    public function test_generates_response_definitions(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $getUser = $spec['paths']['/users/{id}']['get'];
        $this->assertArrayHasKey('responses', $getUser);
        $this->assertArrayHasKey(200, $getUser['responses']);
        $this->assertArrayHasKey(404, $getUser['responses']);
        $this->assertSame('User details', $getUser['responses'][200]['description']);
    }

    public function test_generates_response_schema_from_type(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $getUser = $spec['paths']['/users/{id}']['get'];
        $response200 = $getUser['responses'][200];

        $this->assertArrayHasKey('content', $response200);
        $this->assertArrayHasKey('application/json', $response200['content']);

        $schema = $response200['content']['application/json']['schema'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
    }

    public function test_generates_request_body_from_dto_parameter(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);
        $spec = $doc->toArray();

        $postUsers = $spec['paths']['/users']['post'];
        $this->assertArrayHasKey('requestBody', $postUsers);
        $this->assertTrue($postUsers['requestBody']['required']);

        $bodySchema = $postUsers['requestBody']['content']['application/json']['schema'];
        $this->assertSame('object', $bodySchema['type']);
        $this->assertArrayHasKey('name', $bodySchema['properties']);
        $this->assertArrayHasKey('email', $bodySchema['properties']);
    }

    public function test_schema_generator_produces_required_fields(): void
    {
        $schemaGen = new SchemaGenerator();
        $schema = $schemaGen->fromClass(\Tests\Integration\Fixtures\UserDto::class);

        $this->assertContains('id', $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('email', $schema['required']);
        // bio is nullable, so it should NOT be required
        $this->assertNotContains('bio', $schema['required']);
    }

    public function test_schema_generator_maps_php_types_to_openapi_types(): void
    {
        $schemaGen = new SchemaGenerator();
        $schema = $schemaGen->fromClass(\Tests\Integration\Fixtures\UserDto::class);

        $this->assertSame('integer', $schema['properties']['id']['type']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('string', $schema['properties']['email']['type']);
        // Nullable string should be ['string', 'null']
        $this->assertSame(['string', 'null'], $schema['properties']['bio']['type']);
    }

    public function test_document_serializes_to_valid_json(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);

        $json = $doc->toJson();
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.1.0', $decoded['openapi']);
        $this->assertIsArray($decoded['paths']);
    }

    public function test_document_serializes_to_yaml(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);

        $yaml = $doc->toYaml();

        $this->assertStringContainsString('openapi: 3.1.0', $yaml);
        $this->assertStringContainsString('title: Test API', $yaml);
        $this->assertStringContainsString('/users:', $yaml);
    }

    public function test_custom_schemas_are_included_in_components(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes, [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer'],
                    'message' => ['type' => 'string'],
                ],
            ],
        ]);

        $spec = $doc->toArray();
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);
        $this->assertArrayHasKey('Error', $spec['components']['schemas']);
    }

    public function test_security_schemes_can_be_added(): void
    {
        $routes = $this->buildRoutePayload();
        $doc = $this->generator->generate($routes);

        $doc->addSecurityScheme('bearerAuth', [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        $spec = $doc->toArray();
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $this->assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
    }

    /**
     * Build the route payload array expected by OpenApiGenerator::generate().
     *
     * @return array<int, array{path: string, method: string, controller: string, action: string}>
     */
    private function buildRoutePayload(): array
    {
        $routeDefinitions = $this->routeCollector->collectFromClass(UserController::class);

        return array_map(
            fn (RouteDefinition $r) => [
                'path' => $r->path,
                'method' => $r->httpMethod,
                'controller' => $r->controllerClass,
                'action' => $r->methodName,
            ],
            $routeDefinitions,
        );
    }
}
