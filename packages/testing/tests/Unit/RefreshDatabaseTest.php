<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests\Unit;

use Lattice\Testing\TestCase;
use Lattice\Testing\Traits\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

final class RefreshDatabaseTest extends PHPUnitTestCase
{
    #[Test]
    public function test_migrations_table_is_created_on_setup(): void
    {
        $tc = new RefreshDatabaseStub('test_migrations_table_is_created_on_setup');
        $tc->setUp();

        $pdo = $tc->getTestPdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchAll();

        $this->assertCount(1, $tables);

        $tc->tearDown();
    }

    #[Test]
    public function test_teardown_truncates_non_protected_tables(): void
    {
        $tc = new RefreshDatabaseStub('test_teardown_truncates_non_protected_tables');
        $tc->setUp();

        $pdo = $tc->getTestPdo();
        $pdo->exec('CREATE TABLE "posts" ("id" INTEGER PRIMARY KEY, "title" TEXT)');
        $pdo->exec('INSERT INTO "posts" ("title") VALUES (\'Hello\')');

        // Verify data exists
        $count = (int) $pdo->query('SELECT COUNT(*) FROM "posts"')->fetchColumn();
        $this->assertSame(1, $count);

        // tearDown should truncate
        $tc->tearDown();

        // Re-check after teardown — posts table should be empty
        // Note: tearDown runs truncateAllTables, which DELETEs rows but table still exists
        $count = (int) $pdo->query('SELECT COUNT(*) FROM "posts"')->fetchColumn();
        $this->assertSame(0, $count);
    }

    #[Test]
    public function test_teardown_does_not_truncate_migrations_table(): void
    {
        $tc = new RefreshDatabaseStub('test_teardown_does_not_truncate_migrations_table');
        $tc->setUp();

        $pdo = $tc->getTestPdo();
        $pdo->exec('INSERT INTO "migrations" ("migration", "batch") VALUES (\'2024_01_01_create_users\', 1)');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM "migrations"')->fetchColumn();
        $this->assertSame(1, $count);

        $tc->tearDown();

        // migrations table should still have the row
        $count = (int) $pdo->query('SELECT COUNT(*) FROM "migrations"')->fetchColumn();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function test_custom_migrations_run_on_setup(): void
    {
        $tc = new RefreshDatabaseWithCustomMigrationsStub('test_custom_migrations_run_on_setup');
        $tc->setUp();

        $pdo = $tc->getTestPdo();

        // The custom migration should have created a 'tasks' table
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tasks'")->fetchAll();
        $this->assertCount(1, $tables);

        $tc->tearDown();
    }
}

// --- Test Stubs ---

class RefreshDatabaseStub extends TestCase
{
    use RefreshDatabase;

    private \PDO $pdo;

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function createApplication(): ?object
    {
        return new FakeApp();
    }

    public function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    protected function getDatabaseConnection(): \PDO
    {
        return $this->pdo;
    }

    public function getTestPdo(): \PDO
    {
        return $this->pdo;
    }
}

class RefreshDatabaseWithCustomMigrationsStub extends RefreshDatabaseStub
{
    protected function runApplicationMigrations(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS "tasks" ("id" INTEGER PRIMARY KEY, "title" TEXT NOT NULL)');
    }
}
