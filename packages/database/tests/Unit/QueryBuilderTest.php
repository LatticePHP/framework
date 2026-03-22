<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\ConnectionConfig;
use Lattice\Database\Query\QueryBuilder;
use Lattice\Database\SqliteConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private SqliteConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new SqliteConnection(ConnectionConfig::sqlite(':memory:'));
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT, age INTEGER)');
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 30]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Charlie', 'charlie@example.com', 35]);
    }

    private function query(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    #[Test]
    public function it_selects_all_rows(): void
    {
        $results = $this->query()->table('users')->get();
        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_selects_specific_columns(): void
    {
        $results = $this->query()->table('users')->select('name', 'email')->get();
        $this->assertCount(3, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('email', $results[0]);
        $this->assertArrayNotHasKey('id', $results[0]);
    }

    #[Test]
    public function it_filters_with_where(): void
    {
        $results = $this->query()->table('users')->where('name', '=', 'Alice')->get();
        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]['name']);
    }

    #[Test]
    public function it_defaults_where_operator_to_equals(): void
    {
        $results = $this->query()->table('users')->where('name', 'Bob')->get();
        $this->assertCount(1, $results);
        $this->assertSame('Bob', $results[0]['name']);
    }

    #[Test]
    public function it_chains_multiple_where_clauses(): void
    {
        $results = $this->query()
            ->table('users')
            ->where('age', '>', 24)
            ->where('age', '<', 31)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_orders_results(): void
    {
        $results = $this->query()->table('users')->orderBy('age', 'DESC')->get();
        $this->assertSame('Charlie', $results[0]['name']);
        $this->assertSame('Alice', $results[1]['name']);
        $this->assertSame('Bob', $results[2]['name']);
    }

    #[Test]
    public function it_limits_results(): void
    {
        $results = $this->query()->table('users')->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_offsets_results(): void
    {
        $results = $this->query()->table('users')->orderBy('id')->limit(2)->offset(1)->get();
        $this->assertCount(2, $results);
        $this->assertSame('Bob', $results[0]['name']);
    }

    #[Test]
    public function it_returns_first_result(): void
    {
        $result = $this->query()->table('users')->orderBy('id')->first();
        $this->assertNotNull($result);
        $this->assertSame('Alice', $result['name']);
    }

    #[Test]
    public function it_returns_null_when_first_finds_nothing(): void
    {
        $result = $this->query()->table('users')->where('name', 'Nobody')->first();
        $this->assertNull($result);
    }

    #[Test]
    public function it_inserts_a_row(): void
    {
        $id = $this->query()->table('users')->insert([
            'name' => 'Diana',
            'email' => 'diana@example.com',
            'age' => 28,
        ]);

        $this->assertSame(4, $id);
        $results = $this->query()->table('users')->get();
        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_updates_rows(): void
    {
        $affected = $this->query()->table('users')->where('name', 'Alice')->update(['age' => 31]);

        $this->assertSame(1, $affected);

        $result = $this->query()->table('users')->where('name', 'Alice')->first();
        $this->assertEquals(31, $result['age']);
    }

    #[Test]
    public function it_deletes_rows(): void
    {
        $affected = $this->query()->table('users')->where('name', 'Bob')->delete();

        $this->assertSame(1, $affected);
        $results = $this->query()->table('users')->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_counts_rows(): void
    {
        $count = $this->query()->table('users')->count();
        $this->assertSame(3, $count);
    }

    #[Test]
    public function it_counts_with_where(): void
    {
        $count = $this->query()->table('users')->where('age', '>', 27)->count();
        $this->assertSame(2, $count);
    }
}
