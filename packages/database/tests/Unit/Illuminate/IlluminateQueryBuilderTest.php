<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit\Illuminate;

use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Illuminate\IlluminateQueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IlluminateQueryBuilderTest extends TestCase
{
    private IlluminateDatabaseManager $db;

    protected function setUp(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        $this->db = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->db->schema()->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age');
        });

        $this->db->table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30]);
        $this->db->table('users')->insert(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25]);
        $this->db->table('users')->insert(['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35]);
    }

    private function query(): IlluminateQueryBuilder
    {
        return new IlluminateQueryBuilder($this->db->table('users'));
    }

    #[Test]
    public function it_selects_all_rows(): void
    {
        $results = $this->query()->get();
        $this->assertCount(3, $results);
        $this->assertIsArray($results[0]);
    }

    #[Test]
    public function it_selects_specific_columns(): void
    {
        $results = $this->query()->select('name', 'email')->get();
        $this->assertCount(3, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('email', $results[0]);
        $this->assertArrayNotHasKey('id', $results[0]);
    }

    #[Test]
    public function it_filters_with_where(): void
    {
        $results = $this->query()->where('name', '=', 'Alice')->get();
        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]['name']);
    }

    #[Test]
    public function it_supports_two_argument_where(): void
    {
        $results = $this->query()->where('name', 'Bob')->get();
        $this->assertCount(1, $results);
        $this->assertSame('Bob', $results[0]['name']);
    }

    #[Test]
    public function it_chains_multiple_where_clauses(): void
    {
        $results = $this->query()
            ->where('age', '>', 24)
            ->where('age', '<', 31)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_supports_where_in(): void
    {
        $results = $this->query()->whereIn('name', ['Alice', 'Charlie'])->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_orders_results(): void
    {
        $results = $this->query()->orderBy('age', 'desc')->get();
        $this->assertSame('Charlie', $results[0]['name']);
        $this->assertSame('Alice', $results[1]['name']);
        $this->assertSame('Bob', $results[2]['name']);
    }

    #[Test]
    public function it_limits_results(): void
    {
        $results = $this->query()->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_offsets_results(): void
    {
        $results = $this->query()->orderBy('id')->limit(2)->offset(1)->get();
        $this->assertCount(2, $results);
        $this->assertSame('Bob', $results[0]['name']);
    }

    #[Test]
    public function it_returns_first_result(): void
    {
        $result = $this->query()->orderBy('id')->first();
        $this->assertNotNull($result);
        $this->assertSame('Alice', $result['name']);
    }

    #[Test]
    public function it_returns_null_when_first_finds_nothing(): void
    {
        $result = $this->query()->where('name', 'Nobody')->first();
        $this->assertNull($result);
    }

    #[Test]
    public function it_inserts_a_row(): void
    {
        $id = $this->query()->insertGetId([
            'name' => 'Diana',
            'email' => 'diana@example.com',
            'age' => 28,
        ]);

        $this->assertSame(4, $id);
        $results = $this->query()->get();
        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_updates_rows(): void
    {
        $affected = $this->query()->where('name', 'Alice')->update(['age' => 31]);
        $this->assertSame(1, $affected);

        $result = $this->query()->where('name', 'Alice')->first();
        $this->assertEquals(31, $result['age']);
    }

    #[Test]
    public function it_deletes_rows(): void
    {
        $affected = $this->query()->where('name', 'Bob')->delete();
        $this->assertSame(1, $affected);
        $this->assertCount(2, $this->query()->get());
    }

    #[Test]
    public function it_counts_rows(): void
    {
        $this->assertSame(3, $this->query()->count());
    }

    #[Test]
    public function it_counts_with_where(): void
    {
        $count = $this->query()->where('age', '>', 27)->count();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_checks_existence(): void
    {
        $this->assertTrue($this->query()->where('name', 'Alice')->exists());
        $this->assertFalse($this->query()->where('name', 'Nobody')->exists());
    }

    #[Test]
    public function it_computes_max(): void
    {
        $max = $this->query()->max('age');
        $this->assertEquals(35, $max);
    }

    #[Test]
    public function it_computes_min(): void
    {
        $min = $this->query()->min('age');
        $this->assertEquals(25, $min);
    }

    #[Test]
    public function it_computes_sum(): void
    {
        $sum = $this->query()->sum('age');
        $this->assertEquals(90, $sum);
    }

    #[Test]
    public function it_exposes_illuminate_builder(): void
    {
        $builder = $this->query()->getIlluminateBuilder();
        $this->assertInstanceOf(\Illuminate\Database\Query\Builder::class, $builder);
    }
}
