<?php

declare(strict_types=1);

namespace Lattice\Database\Migration;

use Lattice\Database\ConnectionInterface;
use Lattice\Database\Schema\Blueprint;
use Lattice\Database\Schema\SchemaBuilder;

final class MigrationRunner
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly SchemaBuilder $schema,
    ) {}

    public function run(string $migrationsPath): void
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles($migrationsPath);

        foreach ($files as $file) {
            $filename = basename($file);

            if (in_array($filename, $applied, true)) {
                continue;
            }

            $migration = $this->loadMigration($file);
            $migration->up($this->schema);

            $this->connection->execute(
                'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
                [$filename, $this->getNextBatch()],
            );
        }
    }

    public function rollback(string $migrationsPath): void
    {
        $this->ensureMigrationsTable();

        $lastBatch = $this->getLastBatch();
        if ($lastBatch === 0) {
            return;
        }

        $applied = $this->connection->query(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC',
            [$lastBatch],
        );

        foreach ($applied as $row) {
            $file = $migrationsPath . '/' . $row['migration'];

            if (!file_exists($file)) {
                continue;
            }

            $migration = $this->loadMigration($file);
            $migration->down($this->schema);

            $this->connection->execute(
                'DELETE FROM migrations WHERE migration = ?',
                [$row['migration']],
            );
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS migrations ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'migration VARCHAR(255) NOT NULL, '
            . 'batch INTEGER NOT NULL'
            . ')'
        );
    }

    /**
     * @return string[]
     */
    private function getAppliedMigrations(): array
    {
        $rows = $this->connection->query('SELECT migration FROM migrations');

        return array_map(fn(array $row) => $row['migration'], $rows);
    }

    /**
     * @return string[]
     */
    private function getMigrationFiles(string $path): array
    {
        $files = glob($path . '/*.php');

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    private function loadMigration(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration file {$file} must return a Migration instance.");
        }

        return $migration;
    }

    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    private function getLastBatch(): int
    {
        $result = $this->connection->query('SELECT MAX(batch) as max_batch FROM migrations');

        return (int) ($result[0]['max_batch'] ?? 0);
    }
}
