<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class DatabaseSchemaTool implements McpToolInterface
{
    /**
     * @var array<string, array{columns: array<string, array{type: string, nullable: bool, default: mixed}>, indexes: list<string>}> Mock schema for testing
     */
    private array $schema = [];

    public function getName(): string
    {
        return 'db_schema';
    }

    public function getDescription(): string
    {
        return 'List all database tables with columns, types, indexes, and foreign keys. Describe a specific table in detail.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Specific table to describe. Omit to list all tables.',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * @param array<string, array{columns: array<string, array{type: string, nullable: bool, default: mixed}>, indexes: list<string>}> $schema
     */
    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }

    public function execute(array $arguments): array
    {
        $table = $arguments['table'] ?? null;

        if (is_string($table) && $table !== '') {
            return $this->describeTable($table);
        }

        return $this->listTables();
    }

    private function listTables(): array
    {
        $tables = [];

        foreach ($this->schema as $name => $info) {
            $tables[] = [
                'name' => $name,
                'columns' => count($info['columns']),
                'indexes' => count($info['indexes']),
            ];
        }

        return [
            'total' => count($tables),
            'tables' => $tables,
        ];
    }

    private function describeTable(string $table): array
    {
        if (!isset($this->schema[$table])) {
            return [
                'error' => 'Table not found: ' . $table,
                'available_tables' => array_keys($this->schema),
            ];
        }

        $info = $this->schema[$table];

        return [
            'table' => $table,
            'columns' => $info['columns'],
            'indexes' => $info['indexes'],
        ];
    }
}
