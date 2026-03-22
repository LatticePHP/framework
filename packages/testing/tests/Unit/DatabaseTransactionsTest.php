<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests\Unit;

use Lattice\Testing\TestCase;
use Lattice\Testing\Traits\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

final class DatabaseTransactionsTest extends PHPUnitTestCase
{
    #[Test]
    public function test_setup_begins_transaction(): void
    {
        $tc = new DatabaseTransactionsStub('test_setup_begins_transaction');
        $tc->setUp();

        $this->assertTrue($tc->getTestPdo()->inTransaction());

        $tc->tearDown();
    }

    #[Test]
    public function test_teardown_rolls_back_transaction(): void
    {
        $tc = new DatabaseTransactionsStub('test_teardown_rolls_back_transaction');
        $tc->setUp();

        $pdo = $tc->getTestPdo();

        // Create table and insert data within the transaction
        $pdo->exec('CREATE TABLE "items" ("id" INTEGER PRIMARY KEY, "name" TEXT)');
        $pdo->exec('INSERT INTO "items" ("name") VALUES (\'Widget\')');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM "items"')->fetchColumn();
        $this->assertSame(1, $count);

        // tearDown rolls back — in SQLite, DDL is also transactional
        $tc->tearDown();

        // After rollback, the table should not exist (SQLite rolls back DDL too)
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='items'")->fetchAll();
        $this->assertCount(0, $tables);
    }

    #[Test]
    public function test_teardown_is_safe_when_no_transaction(): void
    {
        $tc = new DatabaseTransactionsStub('test_teardown_is_safe_when_no_transaction');
        $tc->setUp();

        // Manually commit the transaction
        $tc->getTestPdo()->commit();

        // tearDown should not throw even though there's no active transaction
        $tc->tearDown();

        $this->assertFalse($tc->getTestPdo()->inTransaction());
    }
}

// --- Test Stub ---

class DatabaseTransactionsStub extends TestCase
{
    use DatabaseTransactions;

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
