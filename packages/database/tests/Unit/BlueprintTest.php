<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlueprintTest extends TestCase
{
    #[Test]
    public function it_defines_an_auto_increment_id(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->id();

        $columns = $blueprint->getColumns();
        $this->assertCount(1, $columns);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertSame('integer', $columns[0]['type']);
        $this->assertTrue($columns[0]['autoIncrement']);
        $this->assertTrue($columns[0]['primary']);
    }

    #[Test]
    public function it_defines_a_custom_id_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->id('user_id');

        $columns = $blueprint->getColumns();
        $this->assertSame('user_id', $columns[0]['name']);
    }

    #[Test]
    public function it_defines_string_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('name', 100);

        $columns = $blueprint->getColumns();
        $this->assertSame('name', $columns[0]['name']);
        $this->assertSame('string', $columns[0]['type']);
        $this->assertSame(100, $columns[0]['length']);
    }

    #[Test]
    public function it_defines_text_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->text('bio');

        $columns = $blueprint->getColumns();
        $this->assertSame('text', $columns[0]['type']);
    }

    #[Test]
    public function it_defines_integer_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->integer('age');

        $columns = $blueprint->getColumns();
        $this->assertSame('integer', $columns[0]['type']);
    }

    #[Test]
    public function it_defines_big_integer_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->bigInteger('views');

        $columns = $blueprint->getColumns();
        $this->assertSame('bigInteger', $columns[0]['type']);
    }

    #[Test]
    public function it_defines_float_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->float('rating');

        $columns = $blueprint->getColumns();
        $this->assertSame('float', $columns[0]['type']);
    }

    #[Test]
    public function it_defines_boolean_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->boolean('active');

        $columns = $blueprint->getColumns();
        $this->assertSame('boolean', $columns[0]['type']);
    }

    #[Test]
    public function it_defines_timestamp_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestamp('created_at');

        $columns = $blueprint->getColumns();
        $this->assertSame('timestamp', $columns[0]['type']);
    }

    #[Test]
    public function it_defines_timestamps_shortcut(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestamps();

        $columns = $blueprint->getColumns();
        $this->assertCount(2, $columns);
        $this->assertSame('created_at', $columns[0]['name']);
        $this->assertSame('updated_at', $columns[1]['name']);
        $this->assertTrue($columns[0]['nullable']);
        $this->assertTrue($columns[1]['nullable']);
    }

    #[Test]
    public function it_defines_json_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->json('metadata');

        $columns = $blueprint->getColumns();
        $this->assertSame('json', $columns[0]['type']);
    }

    #[Test]
    public function it_applies_nullable_modifier(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('nickname')->nullable();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['nullable']);
    }

    #[Test]
    public function it_applies_default_modifier(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->integer('age')->default(0);

        $columns = $blueprint->getColumns();
        $this->assertSame(0, $columns[0]['default']);
    }

    #[Test]
    public function it_applies_unique_modifier(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('email')->unique();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['unique']);
    }

    #[Test]
    public function it_applies_index_modifier(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('email')->index();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['index']);
    }

    #[Test]
    public function it_applies_primary_modifier(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('uuid')->primary();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['primary']);
    }

    #[Test]
    public function it_defines_foreign_id(): void
    {
        $blueprint = new Blueprint('posts');
        $blueprint->foreignId('user_id')->references('id')->on('users');

        $columns = $blueprint->getColumns();
        $this->assertSame('user_id', $columns[0]['name']);
        $this->assertSame('bigInteger', $columns[0]['type']);

        $foreignKeys = $blueprint->getForeignKeys();
        $this->assertCount(1, $foreignKeys);
        $this->assertSame('user_id', $foreignKeys[0]['column']);
        $this->assertSame('id', $foreignKeys[0]['references']);
        $this->assertSame('users', $foreignKeys[0]['on']);
    }

    #[Test]
    public function it_chains_modifiers(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->string('email', 200)->nullable()->unique();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['nullable']);
        $this->assertTrue($columns[0]['unique']);
        $this->assertSame(200, $columns[0]['length']);
    }

    #[Test]
    public function it_returns_table_name(): void
    {
        $blueprint = new Blueprint('users');
        $this->assertSame('users', $blueprint->getTable());
    }
}
