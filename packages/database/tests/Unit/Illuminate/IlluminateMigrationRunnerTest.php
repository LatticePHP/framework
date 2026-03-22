<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit\Illuminate;

use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Illuminate\IlluminateMigrationRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IlluminateMigrationRunnerTest extends TestCase
{
    private IlluminateDatabaseManager $db;
    private IlluminateMigrationRunner $runner;
    private string $migrationsPath;

    protected function setUp(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        $this->db = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->runner = new IlluminateMigrationRunner($this->db);

        // Create a temporary migrations directory
        $this->migrationsPath = sys_get_temp_dir() . '/lattice_illuminate_migrations_' . uniqid();
        mkdir($this->migrationsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!isset($this->migrationsPath)) {
            return;
        }
        // Clean up migration files
        $files = glob($this->migrationsPath . '/*.php');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->migrationsPath)) {
            rmdir($this->migrationsPath);
        }
    }

    #[Test]
    public function it_creates_migrations_table_automatically(): void
    {
        $this->runner->run($this->migrationsPath);

        $this->assertTrue($this->db->schema()->hasTable('migrations'));
    }

    #[Test]
    public function it_runs_migrations(): void
    {
        $this->createMigrationFile('001_create_posts.php', <<<'PHP'
<?php
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;

return new class {
    public function up(IlluminateSchemaBuilder $schema): void
    {
        $schema->create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(IlluminateSchemaBuilder $schema): void
    {
        $schema->drop('posts');
    }
};
PHP);

        $this->runner->run($this->migrationsPath);

        $this->assertTrue($this->db->schema()->hasTable('posts'));
        $this->assertTrue($this->db->schema()->hasColumn('posts', 'title'));
        $this->assertTrue($this->db->schema()->hasColumn('posts', 'body'));
    }

    #[Test]
    public function it_does_not_rerun_applied_migrations(): void
    {
        $this->createMigrationFile('001_create_posts.php', <<<'PHP'
<?php
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;

return new class {
    public function up(IlluminateSchemaBuilder $schema): void
    {
        $schema->create('posts', function ($table) {
            $table->id();
            $table->string('title');
        });
    }

    public function down(IlluminateSchemaBuilder $schema): void
    {
        $schema->drop('posts');
    }
};
PHP);

        $this->runner->run($this->migrationsPath);
        // Running again should not throw (table already exists would throw if re-run)
        $this->runner->run($this->migrationsPath);

        $count = $this->db->table('migrations')->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_rolls_back_last_batch(): void
    {
        $this->createMigrationFile('001_create_posts.php', <<<'PHP'
<?php
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;

return new class {
    public function up(IlluminateSchemaBuilder $schema): void
    {
        $schema->create('posts', function ($table) {
            $table->id();
            $table->string('title');
        });
    }

    public function down(IlluminateSchemaBuilder $schema): void
    {
        $schema->drop('posts');
    }
};
PHP);

        $this->runner->run($this->migrationsPath);
        $this->assertTrue($this->db->schema()->hasTable('posts'));

        $this->runner->rollback($this->migrationsPath);
        $this->assertFalse($this->db->schema()->hasTable('posts'));
    }

    private function createMigrationFile(string $filename, string $content): void
    {
        file_put_contents($this->migrationsPath . '/' . $filename, $content);
    }
}
