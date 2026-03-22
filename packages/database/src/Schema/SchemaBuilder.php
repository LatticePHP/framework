<?php

declare(strict_types=1);

namespace Lattice\Database\Schema;

use Lattice\Database\ConnectionInterface;

final class SchemaBuilder
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $this->buildCreateTableSql($blueprint);
        $this->connection->execute($sql);

        // Create indexes
        foreach ($blueprint->getColumns() as $column) {
            if (!empty($column['unique']) && empty($column['primary'])) {
                $this->connection->execute(
                    "CREATE UNIQUE INDEX idx_{$table}_{$column['name']}_unique ON {$table} ({$column['name']})"
                );
            } elseif (!empty($column['index'])) {
                $this->connection->execute(
                    "CREATE INDEX idx_{$table}_{$column['name']} ON {$table} ({$column['name']})"
                );
            }
        }
    }

    public function drop(string $table): void
    {
        $this->connection->execute("DROP TABLE {$table}");
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->execute("DROP TABLE IF EXISTS {$table}");
    }

    private function buildCreateTableSql(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columnDefs = [];

        foreach ($blueprint->getColumns() as $column) {
            $columnDefs[] = $this->buildColumnDefinition($column);
        }

        // Foreign key constraints
        foreach ($blueprint->getForeignKeys() as $fk) {
            if ($fk['references'] !== '' && $fk['on'] !== '') {
                $columnDefs[] = "FOREIGN KEY ({$fk['column']}) REFERENCES {$fk['on']} ({$fk['references']})";
            }
        }

        $columns = implode(', ', $columnDefs);

        return "CREATE TABLE {$table} ({$columns})";
    }

    /**
     * @param array<string, mixed> $column
     */
    private function buildColumnDefinition(array $column): string
    {
        $name = $column['name'];
        $sqlType = $this->mapType($column['type'], $column);

        $definition = "{$name} {$sqlType}";

        if (!empty($column['autoIncrement']) && !empty($column['primary'])) {
            // SQLite-compatible auto-increment primary key
            if ($this->connection->getDriverName() === 'sqlite') {
                $definition = "{$name} INTEGER PRIMARY KEY AUTOINCREMENT";
                return $definition;
            }
            $definition .= ' AUTO_INCREMENT PRIMARY KEY';
            return $definition;
        }

        if (!empty($column['primary'])) {
            $definition .= ' PRIMARY KEY';
        }

        if (empty($column['nullable']) || $column['nullable'] === false) {
            // Only add NOT NULL if it's not a primary key (already implied)
            if (empty($column['primary'])) {
                $definition .= ' NOT NULL';
            }
        } else {
            $definition .= ' NULL';
        }

        if (array_key_exists('default', $column)) {
            $default = $column['default'];
            if (is_bool($default)) {
                $default = $default ? 1 : 0;
            } elseif (is_string($default)) {
                $default = "'{$default}'";
            }
            $definition .= " DEFAULT {$default}";
        }

        if (!empty($column['unique']) && $this->connection->getDriverName() !== 'sqlite') {
            $definition .= ' UNIQUE';
        }

        return $definition;
    }

    /**
     * @param array<string, mixed> $column
     */
    private function mapType(string $type, array $column): string
    {
        return match ($type) {
            'string' => 'VARCHAR(' . ($column['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'bigInteger' => 'BIGINT',
            'float' => 'REAL',
            'boolean' => 'BOOLEAN',
            'timestamp' => 'TIMESTAMP',
            'json' => 'TEXT', // SQLite doesn't have JSON type; use TEXT
            default => 'TEXT',
        };
    }
}
