<?php

declare(strict_types=1);

namespace Lattice\Testing\Traits;

/**
 * Refresh the database before each test by running migrations
 * and truncating all tables after each test.
 *
 * Requires the test class to extend Lattice\Testing\TestCase
 * and provide a PDO connection via getDatabaseConnection().
 */
trait RefreshDatabase
{
    /** @var list<string> Tables that should never be truncated */
    protected array $protectedTables = ['migrations'];

    protected function setUpRefreshDatabase(): void
    {
        // Clear all booted Eloquent models so traits re-register events with fresh DB
        if (class_exists(\Illuminate\Database\Eloquent\Model::class)) {
            \Illuminate\Database\Eloquent\Model::clearBootedModels();
        }

        // Reset workspace context from previous test
        if (class_exists(\Lattice\Auth\Workspace\WorkspaceContext::class)) {
            \Lattice\Auth\Workspace\WorkspaceContext::reset();
        }

        $this->runMigrations();
    }

    protected function tearDownRefreshDatabase(): void
    {
        $this->truncateAllTables();
    }

    /**
     * Run all pending migrations.
     *
     * Override this method to provide custom migration logic, e.g.,
     * running module-specific migrations or using a migration runner.
     */
    protected function runMigrations(): void
    {
        $pdo = $this->getDatabaseConnection();

        // Create a migrations tracking table if it doesn't exist
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS "migrations" ('
            . '"id" INTEGER PRIMARY KEY AUTOINCREMENT, '
            . '"migration" VARCHAR(255) NOT NULL, '
            . '"batch" INTEGER NOT NULL DEFAULT 1'
            . ')'
        );

        // Subclasses should override this to run actual migrations
        $this->runApplicationMigrations($pdo);
    }

    /**
     * Run application-specific migrations.
     *
     * Override in your application's base TestCase to run
     * migration files or define schema inline.
     */
    protected function runApplicationMigrations(\PDO $pdo): void
    {
        // No-op by default. Override in subclass.
    }

    /**
     * Truncate all tables except protected ones.
     */
    protected function truncateAllTables(): void
    {
        $pdo = $this->getDatabaseConnection();
        $tables = $this->getAllTableNames($pdo);

        foreach ($tables as $table) {
            if (in_array($table, $this->protectedTables, true)) {
                continue;
            }
            $pdo->exec("DELETE FROM \"{$table}\"");
        }
    }

    /**
     * Get all table names from the database.
     *
     * @return list<string>
     */
    private function getAllTableNames(\PDO $pdo): array
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            'mysql' => 'SHOW TABLES',
            'pgsql' => "SELECT tablename FROM pg_tables WHERE schemaname = 'public'",
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
