<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

use Lattice\Mcp\Attributes\Resource;

final class ResourceRegistry
{
    /** @var array<string, ResourceDefinition> keyed by URI template */
    private array $resources = [];

    /**
     * Register a resource definition directly.
     */
    public function register(ResourceDefinition $definition): void
    {
        $this->resources[$definition->uri] = $definition;
    }

    /**
     * Discover #[Resource] methods on a class and register them.
     *
     * @param class-string $className
     */
    public function discover(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(Resource::class);

            if ($attrs === []) {
                continue;
            }

            $resource = $attrs[0]->newInstance();

            $this->register(new ResourceDefinition(
                uri: $resource->uri,
                name: $resource->name ?? $method->getName(),
                description: $resource->description,
                mimeType: $resource->mimeType,
                className: $className,
                methodName: $method->getName(),
            ));
        }
    }

    /**
     * Match a request URI against registered resource templates.
     *
     * @return array{definition: ResourceDefinition, variables: array<string, string>}|null
     */
    public function match(string $uri): ?array
    {
        // Try exact match first
        if (isset($this->resources[$uri])) {
            return ['definition' => $this->resources[$uri], 'variables' => []];
        }

        // Try template matching
        foreach ($this->resources as $definition) {
            if (!$definition->isTemplate()) {
                continue;
            }

            $variables = $this->matchTemplate($definition->uri, $uri);

            if ($variables !== null) {
                return ['definition' => $definition, 'variables' => $variables];
            }
        }

        return null;
    }

    public function has(string $uri): bool
    {
        return isset($this->resources[$uri]);
    }

    public function get(string $uri): ?ResourceDefinition
    {
        return $this->resources[$uri] ?? null;
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function all(): array
    {
        return $this->resources;
    }

    public function count(): int
    {
        return count($this->resources);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toList(): array
    {
        return array_values(array_map(
            static fn(ResourceDefinition $d): array => $d->toArray(),
            $this->resources,
        ));
    }

    /**
     * Get only resource templates (URIs with variables).
     *
     * @return list<array<string, mixed>>
     */
    public function templateList(): array
    {
        $templates = [];

        foreach ($this->resources as $definition) {
            if ($definition->isTemplate()) {
                $templates[] = [
                    'uriTemplate' => $definition->uri,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'mimeType' => $definition->mimeType,
                ];
            }
        }

        return $templates;
    }

    /**
     * Match a URI against a URI template, extracting variables.
     *
     * @return array<string, string>|null
     */
    private function matchTemplate(string $template, string $uri): ?array
    {
        // Convert template to regex: {varName} -> (?P<varName>[^/]+)
        $pattern = preg_replace_callback(
            '/\{(\w+)\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($template, '#'),
        );

        // The preg_quote will also escape the braces, so we need a different approach
        // Split template into parts, rebuild with regex groups
        $pattern = $this->buildPattern($template);

        if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
            $variables = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $variables[$key] = $value;
                }
            }

            return $variables;
        }

        return null;
    }

    private function buildPattern(string $template): string
    {
        $result = '';
        $i = 0;
        $len = strlen($template);

        while ($i < $len) {
            if ($template[$i] === '{') {
                $end = strpos($template, '}', $i);

                if ($end === false) {
                    $result .= preg_quote($template[$i], '#');
                    $i++;

                    continue;
                }

                $varName = substr($template, $i + 1, $end - $i - 1);
                $result .= '(?P<' . $varName . '>[^/]+)';
                $i = $end + 1;
            } else {
                $result .= preg_quote($template[$i], '#');
                $i++;
            }
        }

        return $result;
    }
}
