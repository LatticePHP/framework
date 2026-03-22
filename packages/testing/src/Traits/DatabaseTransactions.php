<?php

declare(strict_types=1);

namespace Lattice\Testing\Traits;

/**
 * Wrap each test in a database transaction that is rolled back after the test.
 *
 * This is faster than RefreshDatabase for tests that don't need
 * schema changes between tests, as it avoids re-running migrations.
 *
 * Requires the test class to extend Lattice\Testing\TestCase
 * and provide a PDO connection via getDatabaseConnection().
 */
trait DatabaseTransactions
{
    protected function setUpDatabaseTransactions(): void
    {
        $pdo = $this->getDatabaseConnection();
        $pdo->beginTransaction();
    }

    protected function tearDownDatabaseTransactions(): void
    {
        $pdo = $this->getDatabaseConnection();

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
