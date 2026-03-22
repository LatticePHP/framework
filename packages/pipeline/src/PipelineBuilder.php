<?php

declare(strict_types=1);

namespace Lattice\Pipeline;

use Lattice\Pipeline\Attributes\UseFilters;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Pipeline\Attributes\UseInterceptors;
use Lattice\Pipeline\Attributes\UsePipes;

final class PipelineBuilder
{
    /**
     * Build a PipelineConfig from class-level and method-level attributes.
     * Method-level attributes override class-level when both are present for the same type.
     */
    public function forHandler(string $class, string $method): PipelineConfig
    {
        $reflectionClass = new \ReflectionClass($class);
        $reflectionMethod = $reflectionClass->getMethod($method);

        return new PipelineConfig(
            guardClasses: $this->resolveAttribute(UseGuards::class, 'guards', $reflectionClass, $reflectionMethod),
            pipeClasses: $this->resolveAttribute(UsePipes::class, 'pipes', $reflectionClass, $reflectionMethod),
            interceptorClasses: $this->resolveAttribute(UseInterceptors::class, 'interceptors', $reflectionClass, $reflectionMethod),
            filterClasses: $this->resolveAttribute(UseFilters::class, 'filters', $reflectionClass, $reflectionMethod),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     */
    private function resolveAttribute(
        string $attributeClass,
        string $property,
        \ReflectionClass $class,
        \ReflectionMethod $method,
    ): array {
        // Method-level overrides class-level
        $methodAttrs = $method->getAttributes($attributeClass);
        if (!empty($methodAttrs)) {
            return $methodAttrs[0]->newInstance()->$property;
        }

        $classAttrs = $class->getAttributes($attributeClass);
        if (!empty($classAttrs)) {
            return $classAttrs[0]->newInstance()->$property;
        }

        return [];
    }
}
