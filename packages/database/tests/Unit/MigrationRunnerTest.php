<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\ConnectionConfig;
use Lattice\Database\Migration\Migration;
use Lattice\Database\Migration\MigrationRunner;
use Lattice\Database\Schema\Blueprint;
use Lattice\Database\Schema\SchemaBuilder;
use Lattice\Database\SqliteConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private SqliteConnection $connection;
    private SchemaBuilder $schema;
    private MigrationRunner $runner;
    private string $migrationsPath;

    protected function setUp(): void
    {
        $this->connection = new SqliteConnection(ConnectionConfig::sqlite(':memory:'));
        $this->schema = new SchemaBuilder($this->connection);
        $this->runner = new MigrationRunner($this->connection, $this->schema);
        $this->migrationsPath = sys_get_temp_dir() . '/lattice_migrations_' . uniqid();
        mkdir($this->migrationsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up migration files
        if (is_dir($this->migrationsPath)) {
            $files = glob($this->migrationsPath . '/*.php');
            if ($files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->migrationsPath);
        }
    }

    private function createMigrationFile(string $filename, string $className, string $upBody, string $downBody): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

use Lattice\Database\Migration\Migration;
use Lattice\Database\Schema\Blueprint;
use Lattice\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder \$schema): void
    {
        {$upBody}
    }

    public function down(SchemaBuilder \$schema): void
    {
        {$downBody}
    }
};
PHP;

        file_put_contents($this->migrationsPath . '/' . $filename, $content);
    }

    #[Test]
    public function it_runs_migrations(): void
    {
        $this->createMigrationFile(
            '001_create_users_table.php',
            'CreateUsersTable',
            <<<'PHP'
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
PHP,
            <<<'PHP'
        $schema->drop('users');
PHP
        );

        $this->runner->run($this->migrationsPath);

        // Verify table was created
        $this->connection->execute("INSERT INTO users (name) VALUES (?)", ['Alice']);
        $rows = $this->connection->query('SELECT * FROM users');
        $this->assertCount(1, $rows);
    }

    #[Test]
    public function it_tracks_applied_migrations(): void
    {
        $this->createMigrationFile(
            '001_create_users_table.php',
            'CreateUsersTable',
            <<<'PHP'
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
PHP,
            <<<'PHP'
        $schema->drop('users');
PHP
        );

        $this->runner->run($this->migrationsPath);

        // Running again should not fail (migration already applied)
        $this->runner->run($this->migrationsPath);

        $migrations = $this->connection->query('SELECT * FROM migrations');
        $this->assertCount(1, $migrations);
    }

    #[Test]
    public function it_rolls_back_migrations(): void
    {
        $this->createMigrationFile(
            '001_create_users_table.php',
            'CreateUsersTable',
            <<<'PHP'
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
PHP,
            <<<'PHP'
        $schema->drop('users');
PHP
        );

        $this->runner->run($this->migrationsPath);
        $this->runner->rollback($this->migrationsPath);

        // Table should be dropped
        $this->expectException(\PDOException::class);
        $this->connection->query('SELECT * FROM users');
    }

    #[Test]
    public function it_runs_migrations_in_order(): void
    {
        $this->createMigrationFile(
            '001_create_users_table.php',
            'CreateUsersTable',
            <<<'PHP'
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
PHP,
            <<<'PHP'
        $schema->drop('users');
PHP
        );

        $this->createMigrationFile(
            '002_create_posts_table.php',
            'CreatePostsTable',
            <<<'PHP'
        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
PHP,
            <<<'PHP'
        $schema->drop('posts');
PHP
        );

        $this->runner->run($this->migrationsPath);

        $migrations = $this->connection->query('SELECT * FROM migrations ORDER BY migration ASC');
        $this->assertCount(2, $migrations);
        $this->assertSame('001_create_users_table.php', $migrations[0]['migration']);
        $this->assertSame('002_create_posts_table.php', $migrations[1]['migration']);
    }
}
