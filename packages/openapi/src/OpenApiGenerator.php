<?php

declare(strict_types=1);

namespace Lattice\OpenApi;

use Lattice\OpenApi\Attributes\ApiOperation;
use Lattice\OpenApi\Attributes\ApiResponse;
use Lattice\OpenApi\Schema\PathItem;
use Lattice\OpenApi\Schema\SchemaGenerator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class OpenApiGenerator
{
    public function __construct(
        private readonly string $title,
        private readonly string $version,
        private readonly SchemaGenerator $schemaGenerator,
        private readonly ?string $description = null,
    ) {}

    /**
     * Generate an OpenAPI document from route definitions.
     *
     * @param array<int, array{path: string, method: string, controller: string, action: string}> $routes
     * @param array<string, array> $schemas Extra component schemas
     */
    public function generate(array $routes, array $schemas = []): OpenApiDocument
    {
        $doc = new OpenApiDocument(
            title: $this->title,
            version: $this->version,
            description: $this->description,
        );

        foreach ($routes as $route) {
            $pathItem = $this->buildPathItem($route);
            if ($pathItem !== null) {
                $doc->addPath($pathItem);
            }
        }

        foreach ($schemas as $name => $schema) {
            $doc->addSchema($name, $schema);
        }

        return $doc;
    }

    private function buildPathItem(array $route): ?PathItem
    {
        $controller = $route['controller'];
        $action = $route['action'];

        if (!class_exists($controller)) {
            return null;
        }

        $reflection = new ReflectionClass($controller);

        if (!$reflection->hasMethod($action)) {
            return null;
        }

        $method = $reflection->getMethod($action);

        $operation = $this->readApiOperation($method);
        $responses = $this->readApiResponses($method);
        $requestBody = $this->inferRequestBody($method);

        return new PathItem(
            path: $route['path'],
            method: strtolower($route['method']),
            operationId: $operation?->operationId,
            summary: $operation?->summary,
            description: $operation?->description,
            tags: $operation?->tags ?? [],
            requestBody: $requestBody,
            responses: $this->buildResponses($responses),
        );
    }

    private function readApiOperation(ReflectionMethod $method): ?ApiOperation
    {
        $attrs = $method->getAttributes(ApiOperation::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * @return ApiResponse[]
     */
    private function readApiResponses(ReflectionMethod $method): array
    {
        $attrs = $method->getAttributes(ApiResponse::class);

        return array_map(fn($attr) => $attr->newInstance(), $attrs);
    }

    /**
     * @return array<int, array>
     */
    private function buildResponses(array $apiResponses): array
    {
        $responses = [];

        foreach ($apiResponses as $apiResponse) {
            $response = [
                'description' => $apiResponse->description ?? 'OK',
            ];

            if ($apiResponse->type !== null && class_exists($apiResponse->type)) {
                $schema = $this->schemaGenerator->fromClass($apiResponse->type);
                $response['content'] = [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ];
            }

            $responses[$apiResponse->status] = $response;
        }

        return $responses;
    }

    private function inferRequestBody(ReflectionMethod $method): ?array
    {
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();

            if (!class_exists($className)) {
                continue;
            }

            $schema = $this->schemaGenerator->fromClass($className);

            return [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        return null;
    }
}
