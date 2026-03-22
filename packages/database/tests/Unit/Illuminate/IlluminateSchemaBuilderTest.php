<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit\Illuminate;

use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IlluminateSchemaBuilderTest extends TestCase
{
    private IlluminateDatabaseManager $db;
    private IlluminateSchemaBuilder $schema;

    protected function setUp(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        $this->db = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->schema = new IlluminateSchemaBuilder($this->db->schema());
    }

    #[Test]
    public function it_creates_a_table(): void
    {
        $this->schema->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        $this->assertTrue($this->schema->hasTable('users'));
    }

    #[Test]
    public function it_checks_if_table_exists(): void
    {
        $this->assertFalse($this->schema->hasTable('nonexistent'));

        $this->schema->create('posts', function ($table): void {
            $table->id();
            $table->string('title');
        });

        $this->assertTrue($this->schema->hasTable('posts'));
    }

    #[Test]
    public function it_checks_if_column_exists(): void
    {
        $this->schema->create('products', function ($table): void {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2);
        });

        $this->assertTrue($this->schema->hasColumn('products', 'name'));
        $this->assertTrue($this->schema->hasColumn('products', 'price'));
        $this->assertFalse($this->schema->hasColumn('products', 'description'));
    }

    #[Test]
    public function it_drops_a_table(): void
    {
        $this->schema->create('temp', function ($table): void {
            $table->id();
        });

        $this->assertTrue($this->schema->hasTable('temp'));

        $this->schema->drop('temp');

        $this->assertFalse($this->schema->hasTable('temp'));
    }

    #[Test]
    public function it_drops_a_table_if_exists(): void
    {
        // Should not throw even if table does not exist
        $this->schema->dropIfExists('nonexistent');

        $this->schema->create('temp2', function ($table): void {
            $table->id();
        });

        $this->schema->dropIfExists('temp2');
        $this->assertFalse($this->schema->hasTable('temp2'));
    }

    #[Test]
    public function it_lists_columns(): void
    {
        $this->schema->create('items', function ($table): void {
            $table->id();
            $table->string('name');
            $table->text('description');
        });

        $columns = $this->schema->getColumnListing('items');
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('description', $columns);
    }

    #[Test]
    public function it_exposes_illuminate_builder(): void
    {
        $builder = $this->schema->getIlluminateBuilder();
        $this->assertInstanceOf(\Illuminate\Database\Schema\Builder::class, $builder);
    }
}
