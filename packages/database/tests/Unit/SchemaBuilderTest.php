<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\ConnectionConfig;
use Lattice\Database\Schema\Blueprint;
use Lattice\Database\Schema\SchemaBuilder;
use Lattice\Database\SqliteConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderTest extends TestCase
{
    private SqliteConnection $connection;
    private SchemaBuilder $schema;

    protected function setUp(): void
    {
        $this->connection = new SqliteConnection(ConnectionConfig::sqlite(':memory:'));
        $this->schema = new SchemaBuilder($this->connection);
    }

    #[Test]
    public function it_creates_a_table(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Verify table exists by inserting a row
        $this->connection->execute("INSERT INTO users (name) VALUES (?)", ['Alice']);
        $rows = $this->connection->query('SELECT * FROM users');
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    #[Test]
    public function it_creates_table_with_various_column_types(): void
    {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('body');
            $table->integer('views')->default(0);
            $table->bigInteger('likes');
            $table->float('rating');
            $table->boolean('published')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
        });

        $this->connection->execute(
            "INSERT INTO posts (title, body, likes, rating, published, metadata) VALUES (?, ?, ?, ?, ?, ?)",
            ['Test', 'Body text', 0, 4.5, 1, '{}']
        );

        $rows = $this->connection->query('SELECT * FROM posts');
        $this->assertCount(1, $rows);
    }

    #[Test]
    public function it_drops_a_table(): void
    {
        $this->schema->create('temp', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->schema->drop('temp');

        $this->expectException(\PDOException::class);
        $this->connection->query('SELECT * FROM temp');
    }

    #[Test]
    public function it_drops_table_if_exists(): void
    {
        // Should not throw even if table doesn't exist
        $this->schema->dropIfExists('nonexistent');

        $this->schema->create('temp', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->schema->dropIfExists('temp');

        $this->expectException(\PDOException::class);
        $this->connection->query('SELECT * FROM temp');
    }

    #[Test]
    public function it_creates_table_with_unique_column(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        });

        $this->connection->execute("INSERT INTO users (email) VALUES (?)", ['alice@example.com']);

        $this->expectException(\PDOException::class);
        $this->connection->execute("INSERT INTO users (email) VALUES (?)", ['alice@example.com']);
    }

    #[Test]
    public function it_creates_table_with_foreign_id(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('user_id')->references('id')->on('users');
        });

        $this->connection->execute("INSERT INTO users (name) VALUES (?)", ['Alice']);
        $this->connection->execute("INSERT INTO posts (title, user_id) VALUES (?, ?)", ['Post 1', 1]);

        $rows = $this->connection->query('SELECT * FROM posts');
        $this->assertCount(1, $rows);
    }
}
