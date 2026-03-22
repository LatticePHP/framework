<?php

declare(strict_types=1);

namespace Lattice\Database\Illuminate;

use Illuminate\Database\Schema\Blueprint;

/**
 * Migration runner that uses Illuminate's schema builder for DDL operations.
 *
 * This runner manages a migrations table and tracks which migrations
 * have been applied, using the Illuminate schema and query builders
 * for all database operations.
 */
final class IlluminateMigrationRunner
{
    public function __construct(
        private readonly IlluminateDatabaseManager $db,
    ) {}

    /**
     * Run all pending migrations from the given path.
     *
     * Each migration file should return a class/object with up(IlluminateSchemaBuilder)
     * and down(IlluminateSchemaBuilder) methods.
     */
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
            $schema = new IlluminateSchemaBuilder($this->db->schema());
            $migration->up($schema);

            $this->db->table('migrations')->insert([
                'migration' => $filename,
                'batch' => $this->getNextBatch(),
            ]);
        }
    }

    /**
     * Rollback the last batch of migrations.
     */
    public function rollback(string $migrationsPath): void
    {
        $this->ensureMigrationsTable();

        $lastBatch = $this->getLastBatch();
        if ($lastBatch === 0) {
            return;
        }

        $applied = $this->db->table('migrations')
            ->where('batch', $lastBatch)
            ->orderBy('migration', 'desc')
            ->get();

        foreach ($applied as $row) {
            $migrationName = is_array($row) ? $row['migration'] : $row->migration;
            $file = $migrationsPath . '/' . $migrationName;

            if (!file_exists($file)) {
                continue;
            }

            $migration = $this->loadMigration($file);
            $schema = new IlluminateSchemaBuilder($this->db->schema());
            $migration->down($schema);

            $this->db->table('migrations')
                ->where('migration', $migrationName)
                ->delete();
        }
    }

    private function ensureMigrationsTable(): void
    {
        $schema = $this->db->schema();

        if (!$schema->hasTable('migrations')) {
            $schema->create('migrations', function (Blueprint $table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    /**
     * @return list<string>
     */
    private function getAppliedMigrations(): array
    {
        return $this->db->table('migrations')
            ->pluck('migration')
            ->all();
    }

    /**
     * @return list<string>
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

    private function loadMigration(string $file): object
    {
        $migration = require $file;

        if (!is_object($migration)) {
            throw new \RuntimeException("Migration file {$file} must return an object with up() and down() methods.");
        }

        return $migration;
    }

    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    private function getLastBatch(): int
    {
        $result = $this->db->table('migrations')->max('batch');

        return (int) ($result ?? 0);
    }
}
