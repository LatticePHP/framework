<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Schema;

use Lattice\GraphQL\Attributes\EnumType;
use Lattice\GraphQL\Attributes\InputType;
use Lattice\GraphQL\Attributes\ObjectType;
use ReflectionClass;
use ReflectionEnum;

final class SchemaBuilder
{
    private TypeRegistry $typeRegistry;
    private FieldResolver $fieldResolver;
    private ResolverDiscovery $resolverDiscovery;

    /** @var array<class-string> */
    private array $objectTypeClasses = [];

    /** @var array<class-string> */
    private array $inputTypeClasses = [];

    /** @var array<class-string> */
    private array $enumTypeClasses = [];

    /** @var array<class-string> */
    private array $resolverClasses = [];

    /** @var array<string, array<string, mixed>> */
    private array $queries = [];

    /** @var array<string, array<string, mixed>> */
    private array $mutations = [];

    public function __construct(
        ?TypeRegistry $typeRegistry = null,
        ?FieldResolver $fieldResolver = null,
        ?ResolverDiscovery $resolverDiscovery = null,
    ) {
        $this->typeRegistry = $typeRegistry ?? new TypeRegistry();
        $this->fieldResolver = $fieldResolver ?? new FieldResolver();
        $this->resolverDiscovery = $resolverDiscovery ?? new ResolverDiscovery();
    }

    /**
     * Register a class annotated with #[ObjectType].
     *
     * @param class-string $className
     */
    public function addObjectType(string $className): self
    {
        $this->objectTypeClasses[] = $className;
        return $this;
    }

    /**
     * Register a class annotated with #[InputType].
     *
     * @param class-string $className
     */
    public function addInputType(string $className): self
    {
        $this->inputTypeClasses[] = $className;
        return $this;
    }

    /**
     * Register a class annotated with #[EnumType].
     *
     * @param class-string $className
     */
    public function addEnumType(string $className): self
    {
        $this->enumTypeClasses[] = $className;
        return $this;
    }

    /**
     * Register a resolver class containing #[Query] and/or #[Mutation] methods.
     *
     * @param class-string $className
     */
    public function addResolver(string $className): self
    {
        $this->resolverClasses[] = $className;
        return $this;
    }

    /**
     * Build the schema definition from all registered classes.
     *
     * @return array{
     *     types: array<string, array<string, mixed>>,
     *     inputTypes: array<string, array<string, mixed>>,
     *     enumTypes: array<string, array<string, mixed>>,
     *     queries: array<string, array<string, mixed>>,
     *     mutations: array<string, array<string, mixed>>
     * }
     */
    public function build(): array
    {
        // 1. Process enum types first (they may be referenced by other types)
        foreach ($this->enumTypeClasses as $className) {
            $this->processEnumType($className);
        }

        // 2. Process object types
        foreach ($this->objectTypeClasses as $className) {
            $this->processObjectType($className);
        }

        // 3. Process input types
        foreach ($this->inputTypeClasses as $className) {
            $this->processInputType($className);
        }

        // 4. Discover queries and mutations from resolver classes
        $this->queries = $this->resolverDiscovery->discoverQueries(
            $this->resolverClasses,
            $this->typeRegistry,
        );

        $this->mutations = $this->resolverDiscovery->discoverMutations(
            $this->resolverClasses,
            $this->typeRegistry,
        );

        return [
            'types' => $this->typeRegistry->getObjectTypes(),
            'inputTypes' => $this->typeRegistry->getInputTypes(),
            'enumTypes' => $this->typeRegistry->getEnumTypes(),
            'queries' => $this->queries,
            'mutations' => $this->mutations,
        ];
    }

    /**
     * Get the type registry used by this builder.
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }

    /**
     * Get the discovered queries after build().
     *
     * @return array<string, array<string, mixed>>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get the discovered mutations after build().
     *
     * @return array<string, array<string, mixed>>
     */
    public function getMutations(): array
    {
        return $this->mutations;
    }

    /**
     * Generate a GraphQL SDL string from the built schema.
     */
    public function toSDL(): string
    {
        $sdl = '';

        // Enum types
        foreach ($this->typeRegistry->getEnumTypes() as $name => $definition) {
            $sdl .= $this->renderEnumSDL($name, $definition);
        }

        // Object types
        foreach ($this->typeRegistry->getObjectTypes() as $name => $definition) {
            $sdl .= $this->renderObjectTypeSDL($name, $definition);
        }

        // Input types
        foreach ($this->typeRegistry->getInputTypes() as $name => $definition) {
            $sdl .= $this->renderInputTypeSDL($name, $definition);
        }

        // Query root type
        if (!empty($this->queries)) {
            $sdl .= $this->renderRootTypeSDL('Query', $this->queries);
        }

        // Mutation root type
        if (!empty($this->mutations)) {
            $sdl .= $this->renderRootTypeSDL('Mutation', $this->mutations);
        }

        return $sdl;
    }

    private function processObjectType(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $attrs = $reflection->getAttributes(ObjectType::class);

        if (empty($attrs)) {
            return;
        }

        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $this->getShortClassName($className);
        $fields = $this->fieldResolver->extractFields($reflection, $this->typeRegistry);

        $this->typeRegistry->registerObjectType($name, $fields, $attr->description);
    }

    private function processInputType(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $attrs = $reflection->getAttributes(InputType::class);

        if (empty($attrs)) {
            return;
        }

        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $this->getShortClassName($className);
        $fields = $this->fieldResolver->extractFields($reflection, $this->typeRegistry);

        $this->typeRegistry->registerInputType($name, $fields, $attr->description);
    }

    private function processEnumType(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $attrs = $reflection->getAttributes(EnumType::class);

        if (empty($attrs)) {
            return;
        }

        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $this->getShortClassName($className);

        $values = [];

        if ($reflection->isEnum()) {
            $enumReflection = new ReflectionEnum($className);
            foreach ($enumReflection->getCases() as $case) {
                $values[$case->getName()] = '';
            }
        }

        $this->typeRegistry->registerEnumType($name, $values, $attr->description);
    }

    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function renderEnumSDL(string $name, array $definition): string
    {
        $sdl = '';

        if (!empty($definition['description'])) {
            $sdl .= '"""' . "\n" . $definition['description'] . "\n" . '"""' . "\n";
        }

        $sdl .= "enum {$name} {\n";

        foreach ($definition['values'] as $value => $desc) {
            $sdl .= "  {$value}\n";
        }

        $sdl .= "}\n\n";

        return $sdl;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function renderObjectTypeSDL(string $name, array $definition): string
    {
        $sdl = '';

        if (!empty($definition['description'])) {
            $sdl .= '"""' . "\n" . $definition['description'] . "\n" . '"""' . "\n";
        }

        $sdl .= "type {$name} {\n";

        foreach ($definition['fields'] as $fieldName => $fieldDef) {
            if (!empty($fieldDef['description'])) {
                $sdl .= '  "' . $fieldDef['description'] . '"' . "\n";
            }
            $sdl .= "  {$fieldName}: {$fieldDef['type']}";
            if (!empty($fieldDef['deprecationReason'])) {
                $sdl .= ' @deprecated(reason: "' . $fieldDef['deprecationReason'] . '")';
            }
            $sdl .= "\n";
        }

        $sdl .= "}\n\n";

        return $sdl;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function renderInputTypeSDL(string $name, array $definition): string
    {
        $sdl = '';

        if (!empty($definition['description'])) {
            $sdl .= '"""' . "\n" . $definition['description'] . "\n" . '"""' . "\n";
        }

        $sdl .= "input {$name} {\n";

        foreach ($definition['fields'] as $fieldName => $fieldDef) {
            $sdl .= "  {$fieldName}: {$fieldDef['type']}\n";
        }

        $sdl .= "}\n\n";

        return $sdl;
    }

    /**
     * @param array<string, array<string, mixed>> $operations
     */
    private function renderRootTypeSDL(string $name, array $operations): string
    {
        $sdl = "type {$name} {\n";

        foreach ($operations as $opName => $opDef) {
            if (!empty($opDef['description'])) {
                $sdl .= '  "' . $opDef['description'] . '"' . "\n";
            }

            $sdl .= "  {$opName}";

            if (!empty($opDef['arguments'])) {
                $args = [];
                foreach ($opDef['arguments'] as $argName => $argDef) {
                    $args[] = "{$argName}: {$argDef['type']}";
                }
                $sdl .= '(' . implode(', ', $args) . ')';
            }

            $sdl .= ": {$opDef['returnType']}";

            if (!empty($opDef['deprecationReason'])) {
                $sdl .= ' @deprecated(reason: "' . $opDef['deprecationReason'] . '")';
            }

            $sdl .= "\n";
        }

        $sdl .= "}\n\n";

        return $sdl;
    }
}
