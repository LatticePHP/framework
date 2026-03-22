<?php

declare(strict_types=1);

namespace Lattice\Mcp\Registry;

use Lattice\Mcp\Attributes\Prompt;
use Lattice\Mcp\Attributes\PromptArgument;

final class PromptRegistry
{
    /** @var array<string, PromptDefinition> */
    private array $prompts = [];

    /**
     * Register a prompt definition directly.
     */
    public function register(PromptDefinition $definition): void
    {
        $this->prompts[$definition->name] = $definition;
    }

    /**
     * Discover #[Prompt] methods on a class and register them.
     *
     * @param class-string $className
     */
    public function discover(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(Prompt::class);

            if ($attrs === []) {
                continue;
            }

            $prompt = $attrs[0]->newInstance();
            $name = $prompt->name ?? $method->getName();
            $description = $prompt->description;

            $arguments = [];

            foreach ($method->getParameters() as $param) {
                $argDescription = '';
                $argRequired = !$param->isDefaultValueAvailable();

                $promptArgAttrs = $param->getAttributes(PromptArgument::class);

                if ($promptArgAttrs !== []) {
                    $promptArg = $promptArgAttrs[0]->newInstance();
                    $argDescription = $promptArg->description;
                    $argRequired = $promptArg->required;
                }

                $arguments[] = new PromptArgumentDefinition(
                    name: $param->getName(),
                    description: $argDescription,
                    required: $argRequired,
                );
            }

            $this->register(new PromptDefinition(
                name: $name,
                description: $description,
                arguments: $arguments,
                className: $className,
                methodName: $method->getName(),
            ));
        }
    }

    public function has(string $name): bool
    {
        return isset($this->prompts[$name]);
    }

    public function get(string $name): ?PromptDefinition
    {
        return $this->prompts[$name] ?? null;
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public function all(): array
    {
        return $this->prompts;
    }

    public function count(): int
    {
        return count($this->prompts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toList(): array
    {
        return array_values(array_map(
            static fn(PromptDefinition $d): array => $d->toArray(),
            $this->prompts,
        ));
    }
}
