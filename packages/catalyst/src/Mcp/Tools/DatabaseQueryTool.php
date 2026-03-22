<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class DatabaseQueryTool implements McpToolInterface
{
    /** @var list<string> SQL statements that are not allowed */
    private const array FORBIDDEN_STATEMENTS = [
        'INSERT',
        'UPDATE',
        'DELETE',
        'DROP',
        'ALTER',
        'TRUNCATE',
        'CREATE',
        'REPLACE',
        'RENAME',
        'GRANT',
        'REVOKE',
    ];

    /** @var \Closure|null Query executor for testing */
    private ?\Closure $queryExecutor = null;

    public function getName(): string
    {
        return 'db_query';
    }

    public function getDescription(): string
    {
        return 'Execute read-only SELECT queries against the application database. INSERT, UPDATE, DELETE, and DDL statements are rejected.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'The SQL SELECT query to execute',
                ],
            ],
            'required' => ['sql'],
        ];
    }

    /**
     * Set a custom query executor (for testing).
     *
     * @param \Closure(string): list<array<string, mixed>> $executor
     */
    public function setQueryExecutor(\Closure $executor): void
    {
        $this->queryExecutor = $executor;
    }

    public function execute(array $arguments): array
    {
        $sql = $arguments['sql'] ?? null;

        if (!is_string($sql) || trim($sql) === '') {
            return ['error' => 'SQL query is required'];
        }

        $sql = trim($sql);
        $validation = $this->validateQuery($sql);

        if ($validation !== null) {
            return ['error' => $validation];
        }

        if ($this->queryExecutor !== null) {
            $results = ($this->queryExecutor)($sql);

            return [
                'rows' => $results,
                'count' => count($results),
            ];
        }

        return ['error' => 'No database connection available'];
    }

    /**
     * Validate that the query is read-only.
     */
    public function validateQuery(string $sql): ?string
    {
        $normalized = strtoupper(trim($sql));

        // Strip leading comments and whitespace
        $normalized = preg_replace('/^(\s*(--[^\n]*\n|\/\*.*?\*\/)\s*)*/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        foreach (self::FORBIDDEN_STATEMENTS as $statement) {
            if (str_starts_with($normalized, $statement)) {
                return 'Forbidden: ' . $statement . ' statements are not allowed. Only SELECT queries are permitted.';
            }
        }

        if (!str_starts_with($normalized, 'SELECT') && !str_starts_with($normalized, 'WITH') && !str_starts_with($normalized, 'EXPLAIN')) {
            return 'Only SELECT, WITH (CTE), and EXPLAIN queries are allowed.';
        }

        return null;
    }
}
