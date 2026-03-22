<?php

declare(strict_types=1);

namespace Lattice\Ai\Tools;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class ToolSchemaGenerator
{
    /**
     * Generate a ToolDefinition from a method with the #[AiToolAttribute].
     */
    public function fromMethod(ReflectionMethod $method): ?ToolDefinition
    {
        $attributes = $method->getAttributes(AiToolAttribute::class);

        if ($attributes === []) {
            return null;
        }

        $attr = $attributes[0]->newInstance();

        $parameters = $this->generateParametersSchema($method);

        return new ToolDefinition(
            name: $attr->name,
            description: $attr->description,
            parameters: $parameters,
        );
    }

    /**
     * Generate a ToolDefinition from a method by name and class.
     *
     * @param class-string $className
     */
    public function fromClassMethod(string $className, string $methodName): ?ToolDefinition
    {
        $method = new ReflectionMethod($className, $methodName);

        return $this->fromMethod($method);
    }

    /**
     * Generate JSON Schema parameters from a method's parameter list.
     *
     * @return array<string, mixed>
     */
    public function generateParametersSchema(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];
        $docParams = $this->parseDocBlockParams($method);

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $schema = $this->parameterToSchema($param);

            // Add description from docblock if available
            if (isset($docParams[$name])) {
                $schema['description'] = $docParams[$name];
            }

            $properties[$name] = $schema;

            if (!$param->isOptional() && !$param->allowsNull()) {
                $required[] = $name;
            }
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * Convert a PHP parameter to a JSON Schema type definition.
     *
     * @return array<string, mixed>
     */
    private function parameterToSchema(ReflectionParameter $param): array
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType) {
            return ['type' => 'string'];
        }

        $schema = $this->phpTypeToJsonSchema($type->getName());

        if ($param->isDefaultValueAvailable()) {
            $schema['default'] = $param->getDefaultValue();
        }

        if ($type->allowsNull()) {
            // JSON Schema nullable
            $schema = [
                'anyOf' => [
                    $schema,
                    ['type' => 'null'],
                ],
            ];
        }

        return $schema;
    }

    /**
     * Map a PHP type name to JSON Schema type.
     *
     * @return array<string, mixed>
     */
    private function phpTypeToJsonSchema(string $phpType): array
    {
        return match ($phpType) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => $this->handleComplexType($phpType),
        };
    }

    /**
     * Handle non-primitive types (enums, classes).
     *
     * @return array<string, mixed>
     */
    private function handleComplexType(string $typeName): array
    {
        if (enum_exists($typeName)) {
            $reflection = new \ReflectionEnum($typeName);

            if ($reflection->isBacked()) {
                $cases = [];
                foreach ($reflection->getCases() as $case) {
                    $cases[] = $case->getBackingValue();
                }

                $backingType = $reflection->getBackingType();
                $jsonType = ($backingType instanceof ReflectionNamedType && $backingType->getName() === 'int')
                    ? 'integer'
                    : 'string';

                return [
                    'type' => $jsonType,
                    'enum' => $cases,
                ];
            }

            // Non-backed enum: use case names
            $cases = array_map(
                static fn (\ReflectionEnumUnitCase $case): string => $case->getName(),
                $reflection->getCases(),
            );

            return [
                'type' => 'string',
                'enum' => $cases,
            ];
        }

        return ['type' => 'string'];
    }

    /**
     * Parse @param tags from a method's docblock.
     *
     * @return array<string, string>
     */
    private function parseDocBlockParams(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();
        if ($docComment === false) {
            return [];
        }

        $params = [];
        $pattern = '/@param\s+\S+\s+\$(\w+)\s+(.*?)(?=\n\s*\*\s*@|\n\s*\*\/)/s';

        if (preg_match_all($pattern, $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = trim($match[2]);
            }
        }

        return $params;
    }
}
