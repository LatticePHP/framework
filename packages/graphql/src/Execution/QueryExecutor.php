<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Execution;

use Lattice\GraphQL\Schema\FieldResolver;
use Lattice\GraphQL\Schema\SchemaBuilder;
use Lattice\GraphQL\Schema\TypeRegistry;

final class QueryExecutor
{
    private ErrorFormatter $errorFormatter;
    private FieldResolver $fieldResolver;

    /** @var array<string, object> */
    private array $resolverInstances = [];

    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        ?ErrorFormatter $errorFormatter = null,
        ?FieldResolver $fieldResolver = null,
    ) {
        $this->errorFormatter = $errorFormatter ?? new ErrorFormatter();
        $this->fieldResolver = $fieldResolver ?? new FieldResolver();
    }

    /**
     * Register a resolver instance for query/mutation execution.
     */
    public function registerResolver(object $resolver): void
    {
        $this->resolverInstances[get_class($resolver)] = $resolver;
    }

    /**
     * Execute a GraphQL request against the built schema.
     */
    public function execute(GraphqlRequest $request): GraphqlResponse
    {
        try {
            $parsed = $this->parse($request->query);

            if ($parsed === null) {
                return GraphqlResponse::error([
                    $this->errorFormatter->formatMessage('Syntax error: unable to parse query'),
                ]);
            }

            $operationType = $parsed['type'];
            $selections = $parsed['selections'];
            $operationName = $request->operationName;

            // If there are named operations, filter to the requested one
            if ($operationName !== null && isset($parsed['operations'])) {
                if (!isset($parsed['operations'][$operationName])) {
                    return GraphqlResponse::error([
                        $this->errorFormatter->formatMessage(
                            "Unknown operation named \"{$operationName}\"",
                        ),
                    ]);
                }
                $operationType = $parsed['operations'][$operationName]['type'];
                $selections = $parsed['operations'][$operationName]['selections'];
            }

            $data = $this->resolveSelections($operationType, $selections, $request->variables);

            return GraphqlResponse::success($data);
        } catch (GraphqlException $e) {
            return GraphqlResponse::error([
                $this->errorFormatter->format($e),
            ]);
        } catch (\Throwable $e) {
            return GraphqlResponse::error([
                $this->errorFormatter->format($e),
            ]);
        }
    }

    /**
     * Parse a simple GraphQL query into its components.
     *
     * Supports: query { field }, query Name { field }, mutation { field },
     * field arguments, nested selections, variable references.
     *
     * @return array{type: string, selections: array<array<string, mixed>>, operations?: array<string, mixed>}|null
     */
    private function parse(string $query): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        // Handle shorthand query syntax: { field }
        if (str_starts_with($query, '{')) {
            $selections = $this->parseSelections($query, 0);
            return [
                'type' => 'query',
                'selections' => $selections['selections'],
            ];
        }

        // Parse named/typed operations
        $operations = [];
        $firstType = 'query';
        $firstSelections = [];
        $pos = 0;

        while ($pos < strlen($query)) {
            $pos = $this->skipWhitespace($query, $pos);
            if ($pos >= strlen($query)) {
                break;
            }

            // Parse operation type
            $type = 'query';
            if (substr($query, $pos, 5) === 'query') {
                $type = 'query';
                $pos += 5;
            } elseif (substr($query, $pos, 8) === 'mutation') {
                $type = 'mutation';
                $pos += 8;
            } else {
                return null;
            }

            $pos = $this->skipWhitespace($query, $pos);

            // Parse optional operation name
            $opName = null;
            if ($pos < strlen($query) && $query[$pos] !== '{' && $query[$pos] !== '(') {
                $nameStart = $pos;
                while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_]/', $query[$pos])) {
                    $pos++;
                }
                $opName = substr($query, $nameStart, $pos - $nameStart);
            }

            $pos = $this->skipWhitespace($query, $pos);

            // Parse optional variable definitions (skip them for now)
            if ($pos < strlen($query) && $query[$pos] === '(') {
                $depth = 1;
                $pos++;
                while ($pos < strlen($query) && $depth > 0) {
                    if ($query[$pos] === '(') {
                        $depth++;
                    } elseif ($query[$pos] === ')') {
                        $depth--;
                    }
                    $pos++;
                }
            }

            $pos = $this->skipWhitespace($query, $pos);

            // Parse selection set
            if ($pos >= strlen($query) || $query[$pos] !== '{') {
                return null;
            }

            $result = $this->parseSelections($query, $pos);
            $selections = $result['selections'];
            $pos = $result['endPos'];

            if (empty($operations) && $opName === null) {
                $firstType = $type;
                $firstSelections = $selections;
            }

            if ($opName !== null) {
                $operations[$opName] = [
                    'type' => $type,
                    'selections' => $selections,
                ];
            } else {
                $firstType = $type;
                $firstSelections = $selections;
            }
        }

        $result = [
            'type' => $firstType,
            'selections' => $firstSelections,
        ];

        if (!empty($operations)) {
            $result['operations'] = $operations;
        }

        return $result;
    }

    /**
     * Parse selection set starting at given position.
     *
     * @return array{selections: array<array<string, mixed>>, endPos: int}
     */
    private function parseSelections(string $query, int $pos): array
    {
        $selections = [];

        // Skip opening brace
        if ($pos < strlen($query) && $query[$pos] === '{') {
            $pos++;
        }

        while ($pos < strlen($query)) {
            $pos = $this->skipWhitespace($query, $pos);

            if ($pos >= strlen($query) || $query[$pos] === '}') {
                $pos++;
                break;
            }

            // Parse field name
            $nameStart = $pos;
            while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_]/', $query[$pos])) {
                $pos++;
            }
            $fieldName = substr($query, $nameStart, $pos - $nameStart);

            if ($fieldName === '') {
                $pos++;
                continue;
            }

            // Check for alias (name: realName)
            $pos = $this->skipWhitespace($query, $pos);
            $alias = null;
            if ($pos < strlen($query) && $query[$pos] === ':') {
                $alias = $fieldName;
                $pos++;
                $pos = $this->skipWhitespace($query, $pos);
                $nameStart = $pos;
                while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_]/', $query[$pos])) {
                    $pos++;
                }
                $fieldName = substr($query, $nameStart, $pos - $nameStart);
                $pos = $this->skipWhitespace($query, $pos);
            }

            // Parse arguments
            $arguments = [];
            if ($pos < strlen($query) && $query[$pos] === '(') {
                $argResult = $this->parseArguments($query, $pos);
                $arguments = $argResult['arguments'];
                $pos = $argResult['endPos'];
            }

            $pos = $this->skipWhitespace($query, $pos);

            // Parse nested selections
            $subSelections = [];
            if ($pos < strlen($query) && $query[$pos] === '{') {
                $subResult = $this->parseSelections($query, $pos);
                $subSelections = $subResult['selections'];
                $pos = $subResult['endPos'];
            }

            $selection = [
                'field' => $fieldName,
                'arguments' => $arguments,
                'selections' => $subSelections,
            ];

            if ($alias !== null) {
                $selection['alias'] = $alias;
            }

            $selections[] = $selection;
        }

        return ['selections' => $selections, 'endPos' => $pos];
    }

    /**
     * Parse argument list: (name: value, name: value).
     *
     * @return array{arguments: array<string, mixed>, endPos: int}
     */
    private function parseArguments(string $query, int $pos): array
    {
        $arguments = [];

        // Skip opening paren
        $pos++;

        while ($pos < strlen($query)) {
            $pos = $this->skipWhitespace($query, $pos);

            if ($pos >= strlen($query) || $query[$pos] === ')') {
                $pos++;
                break;
            }

            // Parse argument name
            $nameStart = $pos;
            while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_]/', $query[$pos])) {
                $pos++;
            }
            $argName = substr($query, $nameStart, $pos - $nameStart);

            $pos = $this->skipWhitespace($query, $pos);

            // Expect colon
            if ($pos < strlen($query) && $query[$pos] === ':') {
                $pos++;
            }

            $pos = $this->skipWhitespace($query, $pos);

            // Parse value
            $valueResult = $this->parseValue($query, $pos);
            $arguments[$argName] = $valueResult['value'];
            $pos = $valueResult['endPos'];

            $pos = $this->skipWhitespace($query, $pos);

            // Skip comma
            if ($pos < strlen($query) && $query[$pos] === ',') {
                $pos++;
            }
        }

        return ['arguments' => $arguments, 'endPos' => $pos];
    }

    /**
     * Parse a single value (string, int, float, boolean, variable reference, enum, object, array).
     *
     * @return array{value: mixed, endPos: int}
     */
    private function parseValue(string $query, int $pos): array
    {
        $pos = $this->skipWhitespace($query, $pos);

        if ($pos >= strlen($query)) {
            return ['value' => null, 'endPos' => $pos];
        }

        // Variable reference
        if ($query[$pos] === '$') {
            $pos++;
            $nameStart = $pos;
            while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_]/', $query[$pos])) {
                $pos++;
            }
            $varName = substr($query, $nameStart, $pos - $nameStart);
            return ['value' => ['$var' => $varName], 'endPos' => $pos];
        }

        // String literal
        if ($query[$pos] === '"') {
            $pos++;
            $value = '';
            while ($pos < strlen($query) && $query[$pos] !== '"') {
                if ($query[$pos] === '\\' && $pos + 1 < strlen($query)) {
                    $pos++;
                    $value .= match ($query[$pos]) {
                        'n' => "\n",
                        't' => "\t",
                        '\\' => '\\',
                        '"' => '"',
                        default => $query[$pos],
                    };
                } else {
                    $value .= $query[$pos];
                }
                $pos++;
            }
            $pos++; // skip closing quote
            return ['value' => $value, 'endPos' => $pos];
        }

        // Array literal
        if ($query[$pos] === '[') {
            $pos++;
            $items = [];
            while ($pos < strlen($query)) {
                $pos = $this->skipWhitespace($query, $pos);
                if ($pos >= strlen($query) || $query[$pos] === ']') {
                    $pos++;
                    break;
                }
                $itemResult = $this->parseValue($query, $pos);
                $items[] = $itemResult['value'];
                $pos = $itemResult['endPos'];
                $pos = $this->skipWhitespace($query, $pos);
                if ($pos < strlen($query) && $query[$pos] === ',') {
                    $pos++;
                }
            }
            return ['value' => $items, 'endPos' => $pos];
        }

        // Object literal
        if ($query[$pos] === '{') {
            $pos++;
            $obj = [];
            while ($pos < strlen($query)) {
                $pos = $this->skipWhitespace($query, $pos);
                if ($pos >= strlen($query) || $query[$pos] === '}') {
                    $pos++;
                    break;
                }
                $keyStart = $pos;
                while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_]/', $query[$pos])) {
                    $pos++;
                }
                $key = substr($query, $keyStart, $pos - $keyStart);
                $pos = $this->skipWhitespace($query, $pos);
                if ($pos < strlen($query) && $query[$pos] === ':') {
                    $pos++;
                }
                $valResult = $this->parseValue($query, $pos);
                $obj[$key] = $valResult['value'];
                $pos = $valResult['endPos'];
                $pos = $this->skipWhitespace($query, $pos);
                if ($pos < strlen($query) && $query[$pos] === ',') {
                    $pos++;
                }
            }
            return ['value' => $obj, 'endPos' => $pos];
        }

        // Number or boolean or null or enum value
        $valueStart = $pos;
        while ($pos < strlen($query) && preg_match('/[a-zA-Z0-9_.\-]/', $query[$pos])) {
            $pos++;
        }
        $raw = substr($query, $valueStart, $pos - $valueStart);

        // Boolean
        if ($raw === 'true') {
            return ['value' => true, 'endPos' => $pos];
        }
        if ($raw === 'false') {
            return ['value' => false, 'endPos' => $pos];
        }
        if ($raw === 'null') {
            return ['value' => null, 'endPos' => $pos];
        }

        // Integer
        if (preg_match('/^-?\d+$/', $raw)) {
            return ['value' => (int) $raw, 'endPos' => $pos];
        }

        // Float
        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            return ['value' => (float) $raw, 'endPos' => $pos];
        }

        // Enum value or identifier
        return ['value' => $raw, 'endPos' => $pos];
    }

    /**
     * Resolve a selection set against queries or mutations.
     *
     * @param array<array<string, mixed>> $selections
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function resolveSelections(string $operationType, array $selections, array $variables): array
    {
        $data = [];
        $operations = $operationType === 'mutation'
            ? $this->schemaBuilder->getMutations()
            : $this->schemaBuilder->getQueries();

        foreach ($selections as $selection) {
            $fieldName = $selection['field'];
            $alias = $selection['alias'] ?? $fieldName;
            $arguments = $this->resolveArguments($selection['arguments'] ?? [], $variables);

            if (!isset($operations[$fieldName])) {
                throw new GraphqlException("Cannot query field \"{$fieldName}\" on type \"{$operationType}\".");
            }

            $operation = $operations[$fieldName];
            $result = $this->invokeResolver($operation, $arguments);

            // If there are sub-selections and the result is an object or array, resolve them
            if (!empty($selection['selections'])) {
                $result = $this->resolveNestedSelections($result, $selection['selections']);
            }

            $data[$alias] = $result;
        }

        return $data;
    }

    /**
     * Resolve variable references in arguments.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function resolveArguments(array $arguments, array $variables): array
    {
        $resolved = [];

        foreach ($arguments as $name => $value) {
            if (is_array($value) && isset($value['$var'])) {
                $resolved[$name] = $variables[$value['$var']] ?? null;
            } else {
                $resolved[$name] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Invoke a resolver method with the given arguments.
     *
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $arguments
     */
    private function invokeResolver(array $operation, array $arguments): mixed
    {
        $className = $operation['class'];
        $methodName = $operation['method'];

        $instance = $this->resolverInstances[$className] ?? null;

        if ($instance === null) {
            throw new GraphqlException("No resolver instance registered for {$className}.");
        }

        $reflection = new \ReflectionMethod($instance, $methodName);
        $params = [];

        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();

            if (array_key_exists($paramName, $arguments)) {
                $params[] = $arguments[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                $params[] = null;
            }
        }

        return $reflection->invokeArgs($instance, $params);
    }

    /**
     * Resolve nested selections against an object or array result.
     *
     * @param array<array<string, mixed>> $selections
     */
    private function resolveNestedSelections(mixed $result, array $selections): mixed
    {
        if ($result === null) {
            return null;
        }

        if (is_array($result) && !$this->isAssociativeArray($result)) {
            // List of items - resolve each
            return array_map(
                fn(mixed $item): mixed => $this->resolveNestedSelections($item, $selections),
                $result,
            );
        }

        $data = [];

        foreach ($selections as $selection) {
            $fieldName = $selection['field'];
            $alias = $selection['alias'] ?? $fieldName;

            if (is_object($result)) {
                $value = $this->fieldResolver->resolveFieldValue($result, $fieldName);
            } elseif (is_array($result)) {
                $value = $result[$fieldName] ?? null;
            } else {
                $value = null;
            }

            if (!empty($selection['selections']) && $value !== null) {
                $value = $this->resolveNestedSelections($value, $selection['selections']);
            }

            $data[$alias] = $value;
        }

        return $data;
    }

    /**
     * Check if an array is associative (has string keys).
     *
     * @param array<mixed> $arr
     */
    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function skipWhitespace(string $query, int $pos): int
    {
        while ($pos < strlen($query) && ctype_space($query[$pos])) {
            $pos++;
        }

        // Skip comments
        if ($pos < strlen($query) && $query[$pos] === '#') {
            while ($pos < strlen($query) && $query[$pos] !== "\n") {
                $pos++;
            }
            return $this->skipWhitespace($query, $pos);
        }

        return $pos;
    }
}
