<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit\Illuminate;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IlluminateDatabaseManagerTest extends TestCase
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
    }

    #[Test]
    public function it_creates_a_connection(): void
    {
        $connection = $this->db->connection();
        $this->assertInstanceOf(Connection::class, $connection);
    }

    #[Test]
    public function it_returns_a_query_builder(): void
    {
        $this->db->schema()->create('test', function ($table): void {
            $table->id();
            $table->string('name');
        });

        $builder = $this->db->table('test');
        $this->assertInstanceOf(Builder::class, $builder);
    }

    #[Test]
    public function it_returns_a_schema_builder(): void
    {
        $schema = $this->db->schema();
        $this->assertInstanceOf(SchemaBuilder::class, $schema);
    }

    #[Test]
    public function it_can_create_tables_and_query_data(): void
    {
        $this->db->schema()->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
        });

        $this->db->table('users')->insert([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->db->table('users')->insert([
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $results = $this->db->table('users')->get();
        $this->assertCount(2, $results);

        $alice = $this->db->table('users')->where('name', 'Alice')->first();
        $this->assertNotNull($alice);
        $this->assertSame('alice@example.com', $alice->email);
    }

    #[Test]
    public function it_can_add_additional_connections(): void
    {
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ], 'secondary');

        $connection = $this->db->connection('secondary');
        $this->assertInstanceOf(Connection::class, $connection);
    }

    #[Test]
    public function it_exposes_capsule(): void
    {
        $capsule = $this->db->getCapsule();
        $this->assertNotNull($capsule);
    }
}
