<?php

declare(strict_types=1);

namespace Lattice\Routing;

use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Head;
use Lattice\Routing\Attributes\Header;
use Lattice\Routing\Attributes\Name;
use Lattice\Routing\Attributes\Options;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Patch;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;
use Lattice\Routing\Attributes\Query;
use Lattice\Routing\Attributes\Version;
use ReflectionClass;
use ReflectionMethod;

final class RouteCollector
{
    /** @var array<string, string> Maps attribute class to HTTP method */
    private const METHOD_MAP = [
        Get::class => 'GET',
        Post::class => 'POST',
        Put::class => 'PUT',
        Patch::class => 'PATCH',
        Delete::class => 'DELETE',
        Head::class => 'HEAD',
        Options::class => 'OPTIONS',
    ];

    /** @var list<string> Pipeline attribute class names to check */
    private const PIPELINE_ATTRIBUTES = [
        'Lattice\\Pipeline\\Attributes\\UseGuards',
        'Lattice\\Pipeline\\Attributes\\UseInterceptors',
        'Lattice\\Pipeline\\Attributes\\UsePipes',
        'Lattice\\Pipeline\\Attributes\\UseFilters',
    ];

    /**
     * @return RouteDefinition[]
     */
    public function collectFromClass(string $controllerClass): array
    {
        $reflection = new ReflectionClass($controllerClass);
        $prefix = $this->getPrefix($reflection);
        $version = $this->getVersion($reflection);
        $classPipeline = $this->getClassPipelineAttributes($reflection);
        $routes = [];

        // Prepend version to prefix if present
        $versionPrefix = '';
        if ($version !== null) {
            $versionPrefix = '/' . $version;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach (self::METHOD_MAP as $attributeClass => $httpMethod) {
                $attrs = $method->getAttributes($attributeClass);
                if (empty($attrs)) {
                    continue;
                }

                $attr = $attrs[0]->newInstance();
                $path = rtrim($versionPrefix . $prefix . $attr->path, '/') ?: '/';
                $bindings = $this->collectParameterBindings($method);
                $name = $this->getMethodName($method);
                $methodPipeline = $this->getMethodPipelineAttributes($method);

                $routes[] = new RouteDefinition(
                    httpMethod: $httpMethod,
                    path: $path,
                    controllerClass: $controllerClass,
                    methodName: $method->getName(),
                    parameterBindings: $bindings,
                    name: $name,
                    guards: array_merge($classPipeline['guards'], $methodPipeline['guards']),
                    interceptors: array_merge($classPipeline['interceptors'], $methodPipeline['interceptors']),
                    pipes: array_merge($classPipeline['pipes'], $methodPipeline['pipes']),
                    filters: array_merge($classPipeline['filters'], $methodPipeline['filters']),
                    version: $version,
                );
            }
        }

        return $routes;
    }

    private function getPrefix(ReflectionClass $reflection): string
    {
        $attrs = $reflection->getAttributes(Controller::class);
        if (empty($attrs)) {
            return '';
        }

        return $attrs[0]->newInstance()->prefix;
    }

    private function getVersion(ReflectionClass $reflection): ?string
    {
        $attrs = $reflection->getAttributes(Version::class);
        if (empty($attrs)) {
            return null;
        }

        $version = $attrs[0]->newInstance()->versions;

        // If array, take first version as the primary
        if (is_array($version)) {
            return $version[0] ?? null;
        }

        return $version;
    }

    /**
     * Extract class-level pipeline attributes (guards, interceptors, pipes, filters).
     *
     * @return array{guards: list<class-string>, interceptors: list<class-string>, pipes: list<class-string>, filters: list<class-string>}
     */
    private function getClassPipelineAttributes(ReflectionClass $reflection): array
    {
        return $this->extractPipelineAttributes($reflection->getAttributes());
    }

    /**
     * Extract method-level pipeline attributes.
     *
     * @return array{guards: list<class-string>, interceptors: list<class-string>, pipes: list<class-string>, filters: list<class-string>}
     */
    private function getMethodPipelineAttributes(ReflectionMethod $method): array
    {
        return $this->extractPipelineAttributes($method->getAttributes());
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     * @return array{guards: list<class-string>, interceptors: list<class-string>, pipes: list<class-string>, filters: list<class-string>}
     */
    private function extractPipelineAttributes(array $attributes): array
    {
        $result = [
            'guards' => [],
            'interceptors' => [],
            'pipes' => [],
            'filters' => [],
        ];

        foreach ($attributes as $attr) {
            $attrName = $attr->getName();

            if ($attrName === 'Lattice\\Pipeline\\Attributes\\UseGuards' && class_exists($attrName)) {
                $result['guards'] = $attr->newInstance()->guards;
            } elseif ($attrName === 'Lattice\\Pipeline\\Attributes\\UseInterceptors' && class_exists($attrName)) {
                $result['interceptors'] = $attr->newInstance()->interceptors;
            } elseif ($attrName === 'Lattice\\Pipeline\\Attributes\\UsePipes' && class_exists($attrName)) {
                $result['pipes'] = $attr->newInstance()->pipes;
            } elseif ($attrName === 'Lattice\\Pipeline\\Attributes\\UseFilters' && class_exists($attrName)) {
                $result['filters'] = $attr->newInstance()->filters;
            }
        }

        return $result;
    }

    /**
     * Extract route name from #[Name] attribute on a method.
     */
    private function getMethodName(ReflectionMethod $method): ?string
    {
        $attrs = $method->getAttributes(Name::class);
        if (empty($attrs)) {
            return null;
        }

        return $attrs[0]->newInstance()->name;
    }

    /**
     * @return ParameterBinding[]
     */
    private function collectParameterBindings(ReflectionMethod $method): array
    {
        $bindings = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();

            // Body
            $bodyAttrs = $param->getAttributes(Body::class);
            if (!empty($bodyAttrs)) {
                $bindings[] = new ParameterBinding(
                    type: 'body',
                    parameterName: $paramName,
                    name: null,
                );
                continue;
            }

            // Query
            $queryAttrs = $param->getAttributes(Query::class);
            if (!empty($queryAttrs)) {
                $query = $queryAttrs[0]->newInstance();
                $bindings[] = new ParameterBinding(
                    type: 'query',
                    parameterName: $paramName,
                    name: $query->name ?? $paramName,
                );
                continue;
            }

            // Param
            $paramAttrs = $param->getAttributes(Param::class);
            if (!empty($paramAttrs)) {
                $paramAttr = $paramAttrs[0]->newInstance();
                $bindings[] = new ParameterBinding(
                    type: 'param',
                    parameterName: $paramName,
                    name: $paramAttr->name ?? $paramName,
                );
                continue;
            }

            // Header
            $headerAttrs = $param->getAttributes(Header::class);
            if (!empty($headerAttrs)) {
                $header = $headerAttrs[0]->newInstance();
                $bindings[] = new ParameterBinding(
                    type: 'header',
                    parameterName: $paramName,
                    name: $header->name,
                );
                continue;
            }

            // CurrentUser
            $currentUserAttrs = $param->getAttributes(CurrentUser::class);
            if (!empty($currentUserAttrs)) {
                $bindings[] = new ParameterBinding(
                    type: 'current_user',
                    parameterName: $paramName,
                    name: null,
                );
                continue;
            }
        }

        return $bindings;
    }
}
