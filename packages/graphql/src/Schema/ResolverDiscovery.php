<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Schema;

use Lattice\GraphQL\Attributes\Mutation;
use Lattice\GraphQL\Attributes\Query;
use ReflectionClass;
use ReflectionMethod;

final class ResolverDiscovery
{
    /**
     * Discover all #[Query] methods from a list of resolver classes.
     *
     * @param array<class-string> $resolverClasses
     * @return array<string, array{class: class-string, method: string, returnType: string, arguments: array<string, array<string, mixed>>, description: ?string, deprecationReason: ?string}>
     */
    public function discoverQueries(array $resolverClasses, TypeRegistry $typeRegistry): array
    {
        $queries = [];

        foreach ($resolverClasses as $className) {
            $reflection = new ReflectionClass($className);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $queryAttrs = $method->getAttributes(Query::class);

                if (empty($queryAttrs)) {
                    continue;
                }

                $queryAttr = $queryAttrs[0]->newInstance();
                $name = $queryAttr->name ?? $method->getName();
                $returnType = $typeRegistry->mapPhpType($method->getReturnType());
                $arguments = $this->extractArguments($method, $typeRegistry);

                $queries[$name] = [
                    'class' => $className,
                    'method' => $method->getName(),
                    'returnType' => $returnType,
                    'arguments' => $arguments,
                    'description' => $queryAttr->description,
                    'deprecationReason' => $queryAttr->deprecationReason,
                ];
            }
        }

        return $queries;
    }

    /**
     * Discover all #[Mutation] methods from a list of resolver classes.
     *
     * @param array<class-string> $resolverClasses
     * @return array<string, array{class: class-string, method: string, returnType: string, arguments: array<string, array<string, mixed>>, description: ?string}>
     */
    public function discoverMutations(array $resolverClasses, TypeRegistry $typeRegistry): array
    {
        $mutations = [];

        foreach ($resolverClasses as $className) {
            $reflection = new ReflectionClass($className);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $mutationAttrs = $method->getAttributes(Mutation::class);

                if (empty($mutationAttrs)) {
                    continue;
                }

                $mutationAttr = $mutationAttrs[0]->newInstance();
                $name = $mutationAttr->name ?? $method->getName();
                $returnType = $typeRegistry->mapPhpType($method->getReturnType());
                $arguments = $this->extractArguments($method, $typeRegistry);

                $mutations[$name] = [
                    'class' => $className,
                    'method' => $method->getName(),
                    'returnType' => $returnType,
                    'arguments' => $arguments,
                    'description' => $mutationAttr->description,
                ];
            }
        }

        return $mutations;
    }

    /**
     * Extract arguments from a method's parameters.
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractArguments(ReflectionMethod $method, TypeRegistry $typeRegistry): array
    {
        $arguments = [];

        foreach ($method->getParameters() as $param) {
            $argAttrs = $param->getAttributes(\Lattice\GraphQL\Attributes\Argument::class);

            if (!empty($argAttrs)) {
                $argAttr = $argAttrs[0]->newInstance();
                $name = $argAttr->name ?? $param->getName();
                $type = $argAttr->type ?? $typeRegistry->mapPhpType($param->getType());

                $arguments[$name] = [
                    'type' => $type,
                    'description' => $argAttr->description,
                    'defaultValue' => $argAttr->defaultValue ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),
                ];
            } else {
                $name = $param->getName();
                $type = $typeRegistry->mapPhpType($param->getType());

                $arguments[$name] = [
                    'type' => $type,
                    'description' => null,
                    'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                ];
            }
        }

        return $arguments;
    }
}
