<?php

declare(strict_types=1);

namespace Lattice\JsonApi;

use ReflectionClass;
use ReflectionProperty;

final class JsonApiSerializer
{
    public function __construct(
        private readonly string $idProperty = 'id',
    ) {}

    public function serialize(object $resource, string $type): JsonApiDocument
    {
        $jsonApiResource = $this->toResource($resource, $type);

        return JsonApiDocument::fromResource($jsonApiResource);
    }

    /**
     * @param object[] $resources
     */
    public function serializeCollection(array $resources, string $type): JsonApiDocument
    {
        $jsonApiResources = array_map(
            fn(object $resource) => $this->toResource($resource, $type),
            $resources,
        );

        return JsonApiDocument::fromCollection($jsonApiResources);
    }

    private function toResource(object $resource, string $type): JsonApiResource
    {
        $reflection = new ReflectionClass($resource);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $id = null;
        $attributes = [];

        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $property->getValue($resource);

            if ($name === $this->idProperty) {
                $id = (string) $value;
                continue;
            }

            $attributes[$name] = $value;
        }

        return new JsonApiResource(
            type: $type,
            id: $id ?? '',
            attributes: $attributes,
        );
    }
}
