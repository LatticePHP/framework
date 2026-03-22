<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

use Lattice\Mcp\Attributes\Tool;
use Lattice\Mcp\Schema\ToolSchemaGenerator;

final class ToolRegistry
{
    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    public function __construct(
        private readonly ToolSchemaGenerator $schemaGenerator = new ToolSchemaGenerator(),
    ) {}

    /**
     * Register a tool definition directly.
     */
    public function register(ToolDefinition $definition): void
    {
        $this->tools[$definition->name] = $definition;
    }

    /**
     * Discover #[Tool] methods on a class and register them.
     *
     * @param class-string $className
     */
    public function discover(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(Tool::class);

            if ($attrs === []) {
                continue;
            }

            $tool = $attrs[0]->newInstance();
            $name = $tool->name ?? $method->getName();
            $description = $tool->description;

            $inputSchema = $this->schemaGenerator->generate($method);

            $this->register(new ToolDefinition(
                name: $name,
                description: $description,
                inputSchema: $inputSchema,
                className: $className,
                methodName: $method->getName(),
            ));
        }
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?ToolDefinition
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, ToolDefinition>
     */
    public function all(): array
    {
        return $this->tools;
    }

    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toList(): array
    {
        return array_values(array_map(
            static fn(ToolDefinition $d): array => $d->toArray(),
            $this->tools,
        ));
    }
}
