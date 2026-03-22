<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\ConnectionConfig;
use Lattice\Database\SqliteConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqliteConnectionTest extends TestCase
{
    private SqliteConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new SqliteConnection(ConnectionConfig::sqlite(':memory:'));
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT)');
    }

    #[Test]
    public function it_executes_insert_and_returns_affected_rows(): void
    {
        $affected = $this->connection->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Alice', 'alice@example.com']
        );

        $this->assertSame(1, $affected);
    }

    #[Test]
    public function it_queries_and_returns_results(): void
    {
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Bob', 'bob@example.com']);

        $results = $this->connection->query('SELECT * FROM users ORDER BY id');

        $this->assertCount(2, $results);
        $this->assertSame('Alice', $results[0]['name']);
        $this->assertSame('Bob', $results[1]['name']);
    }

    #[Test]
    public function it_returns_last_insert_id(): void
    {
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);

        $id = $this->connection->lastInsertId();
        $this->assertEquals(1, $id);
    }

    #[Test]
    public function it_commits_a_transaction(): void
    {
        $result = $this->connection->transaction(function () {
            $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
            return 'done';
        });

        $this->assertSame('done', $result);
        $rows = $this->connection->query('SELECT * FROM users');
        $this->assertCount(1, $rows);
    }

    #[Test]
    public function it_rolls_back_transaction_on_exception(): void
    {
        try {
            $this->connection->transaction(function () {
                $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $rows = $this->connection->query('SELECT * FROM users');
        $this->assertCount(0, $rows);
    }

    #[Test]
    public function it_supports_manual_transaction_control(): void
    {
        $this->connection->beginTransaction();
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
        $this->connection->commit();

        $rows = $this->connection->query('SELECT * FROM users');
        $this->assertCount(1, $rows);
    }

    #[Test]
    public function it_supports_manual_rollback(): void
    {
        $this->connection->beginTransaction();
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
        $this->connection->rollBack();

        $rows = $this->connection->query('SELECT * FROM users');
        $this->assertCount(0, $rows);
    }

    #[Test]
    public function it_returns_driver_name(): void
    {
        $this->assertSame('sqlite', $this->connection->getDriverName());
    }

    #[Test]
    public function it_queries_with_bindings(): void
    {
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Bob', 'bob@example.com']);

        $results = $this->connection->query('SELECT * FROM users WHERE name = ?', ['Bob']);

        $this->assertCount(1, $results);
        $this->assertSame('Bob', $results[0]['name']);
    }
}
