<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Illuminate\IlluminateMigrationRunner;
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;
use Lattice\Database\ModuleMigrationDiscoverer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleMigrationTest extends TestCase
{
    private IlluminateDatabaseManager $db;
    private string $tempDir;
    private string $uniqueId;

    protected function setUp(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        $this->db = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->uniqueId = bin2hex(random_bytes(8));
        $this->tempDir = sys_get_temp_dir() . '/lattice_mig_' . $this->uniqueId;
        $this->createTestModuleStructure();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    // ── Discovery Tests ──────────────────────────────────────────────────

    #[Test]
    public function test_discovers_migrations_from_module_directory(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();

        $moduleClass = $this->loadModuleClass('UserModule');
        $files = $discoverer->discover([$moduleClass]);

        $this->assertCount(2, $files);
        $this->assertStringContainsString('2024_01_01_000001_create_users_table.php', $files[0]);
        $this->assertStringContainsString('2024_01_01_000002_create_profiles_table.php', $files[1]);
    }

    #[Test]
    public function test_discovers_migrations_from_multiple_modules(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();

        $userModule = $this->loadModuleClass('UserModule');
        $orderModule = $this->loadModuleClass('OrderModule');

        $files = $discoverer->discover([$userModule, $orderModule]);

        $this->assertCount(3, $files);
        $this->assertStringContainsString('create_users_table.php', $files[0]);
        $this->assertStringContainsString('create_profiles_table.php', $files[1]);
        $this->assertStringContainsString('create_orders_table.php', $files[2]);
    }

    #[Test]
    public function test_returns_empty_for_nonexistent_module(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();

        $files = $discoverer->discover(['NonExistent\\Module\\Class_' . $this->uniqueId]);

        $this->assertSame([], $files);
    }

    #[Test]
    public function test_discovers_seeders_from_module_directory(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();

        $moduleClass = $this->loadModuleClass('UserModule');
        $files = $discoverer->discoverSeeders([$moduleClass]);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('UserSeeder.php', $files[0]);
    }

    // ── Migration Runner Integration ─────────────────────────────────────

    #[Test]
    public function test_run_migrations_creates_tables(): void
    {
        $migrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);

        $runner->run($migrationsDir);

        $schema = $this->db->schema();
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('profiles'));
        $this->assertTrue($schema->hasTable('migrations'));
    }

    #[Test]
    public function test_run_migrations_records_in_migrations_table(): void
    {
        $migrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);

        $runner->run($migrationsDir);

        $records = $this->db->table('migrations')->get();
        $this->assertCount(2, $records);

        $migrations = $records->pluck('migration')->all();
        $this->assertContains('2024_01_01_000001_create_users_table.php', $migrations);
        $this->assertContains('2024_01_01_000002_create_profiles_table.php', $migrations);
    }

    #[Test]
    public function test_run_migrations_skips_already_applied(): void
    {
        $migrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);

        // Run twice
        $runner->run($migrationsDir);
        $runner->run($migrationsDir);

        // Should still only have 2 records, not 4
        $records = $this->db->table('migrations')->get();
        $this->assertCount(2, $records);
    }

    #[Test]
    public function test_rollback_removes_last_batch_table(): void
    {
        $migrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);

        $runner->run($migrationsDir);

        $schema = $this->db->schema();
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('profiles'));

        // Each migration gets its own batch number, so rollback only removes the last one
        $runner->rollback($migrationsDir);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('profiles'));

        // Rollback again to remove the first migration
        $runner->rollback($migrationsDir);
        $this->assertFalse($schema->hasTable('users'));
    }

    #[Test]
    public function test_rollback_clears_migration_records_incrementally(): void
    {
        $migrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);

        $runner->run($migrationsDir);
        $this->assertCount(2, $this->db->table('migrations')->get());

        // First rollback removes the last batch (1 migration)
        $runner->rollback($migrationsDir);
        $this->assertCount(1, $this->db->table('migrations')->get());

        // Second rollback removes the remaining migration
        $runner->rollback($migrationsDir);
        $this->assertCount(0, $this->db->table('migrations')->get());
    }

    #[Test]
    public function test_multi_module_migration_run_and_rollback(): void
    {
        $userDir = $this->tempDir . '/UserModule/database/migrations';
        $orderDir = $this->tempDir . '/OrderModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);

        // Run user migrations first (batch 1)
        $runner->run($userDir);
        // Run order migrations second (batch 2)
        $runner->run($orderDir);

        $schema = $this->db->schema();
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('profiles'));
        $this->assertTrue($schema->hasTable('orders'));

        // Rollback should only remove the last batch (orders)
        $runner->rollback($orderDir);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('profiles'));
        $this->assertFalse($schema->hasTable('orders'));
    }

    #[Test]
    public function test_fresh_migration_drops_and_recreates(): void
    {
        $migrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $runner = new IlluminateMigrationRunner($this->db);
        $schema = $this->db->schema();

        // Run migrations
        $runner->run($migrationsDir);
        $this->assertTrue($schema->hasTable('users'));

        // Insert data
        $this->db->table('users')->insert([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);
        $this->assertSame(1, $this->db->table('users')->count());

        // Simulate fresh: drop all tables, then re-run
        $tables = $schema->getTables();
        foreach ($tables as $table) {
            $tableName = is_array($table) ? ($table['name'] ?? '') : (string) $table;
            if ($tableName !== '') {
                $schema->dropIfExists($tableName);
            }
        }

        // Re-run
        $runner->run($migrationsDir);
        $this->assertTrue($schema->hasTable('users'));
        // Data should be gone
        $this->assertSame(0, $this->db->table('users')->count());
    }

    // ── Module Discoverer + Runner Combined ──────────────────────────────

    #[Test]
    public function test_discover_and_run_full_pipeline(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();
        $runner = new IlluminateMigrationRunner($this->db);

        $userModule = $this->loadModuleClass('UserModule');
        $orderModule = $this->loadModuleClass('OrderModule');

        $migrationFiles = $discoverer->discover([$userModule, $orderModule]);
        $this->assertCount(3, $migrationFiles);

        // Group by directory and run
        $directories = [];
        foreach ($migrationFiles as $file) {
            $dir = dirname($file);
            $directories[$dir] = true;
        }

        foreach (array_keys($directories) as $dir) {
            $runner->run($dir);
        }

        $schema = $this->db->schema();
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('profiles'));
        $this->assertTrue($schema->hasTable('orders'));

        // Verify migration tracking
        $records = $this->db->table('migrations')->get();
        $this->assertCount(3, $records);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createTestModuleStructure(): void
    {
        $ns = 'LatticeTest\\MigTest_' . $this->uniqueId;

        // UserModule with 2 migrations and 1 seeder
        $userMigrationsDir = $this->tempDir . '/UserModule/database/migrations';
        $userSeedersDir = $this->tempDir . '/UserModule/database/seeders';
        mkdir($userMigrationsDir, 0777, true);
        mkdir($userSeedersDir, 0777, true);

        file_put_contents(
            $userMigrationsDir . '/2024_01_01_000001_create_users_table.php',
            $this->generateMigration('users', ['name' => 'string', 'email' => 'string']),
        );

        file_put_contents(
            $userMigrationsDir . '/2024_01_01_000002_create_profiles_table.php',
            $this->generateMigration('profiles', ['user_id' => 'unsignedBigInteger', 'bio' => 'text']),
        );

        file_put_contents(
            $userSeedersDir . '/UserSeeder.php',
            "<?php\n// UserSeeder placeholder\n",
        );

        // OrderModule with 1 migration
        $orderMigrationsDir = $this->tempDir . '/OrderModule/database/migrations';
        mkdir($orderMigrationsDir, 0777, true);

        file_put_contents(
            $orderMigrationsDir . '/2024_01_02_000001_create_orders_table.php',
            $this->generateMigration('orders', ['user_id' => 'unsignedBigInteger', 'total' => 'decimal']),
        );

        // Create module class files with unique namespaces
        file_put_contents(
            $this->tempDir . '/UserModule/UserModule.php',
            "<?php\nnamespace {$ns};\nclass UserModule {}\n",
        );

        file_put_contents(
            $this->tempDir . '/OrderModule/OrderModule.php',
            "<?php\nnamespace {$ns};\nclass OrderModule {}\n",
        );
    }

    /**
     * Load a temporary module class and return its FQCN.
     */
    private function loadModuleClass(string $name): string
    {
        $file = $this->tempDir . '/' . $name . '/' . $name . '.php';
        require_once $file;

        return 'LatticeTest\\MigTest_' . $this->uniqueId . '\\' . $name;
    }

    /**
     * Generate a migration file that returns an anonymous class using IlluminateSchemaBuilder.
     *
     * @param array<string, string> $columns Column name => type method
     */
    private function generateMigration(string $tableName, array $columns): string
    {
        $columnDefs = '';
        foreach ($columns as $colName => $colType) {
            if ($colType === 'decimal') {
                $columnDefs .= "            \$table->decimal('{$colName}', 10, 2);\n";
            } else {
                $columnDefs .= "            \$table->{$colType}('{$colName}');\n";
            }
        }

        return <<<PHP
<?php

use Illuminate\Database\Schema\Blueprint;
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;

return new class {
    public function up(IlluminateSchemaBuilder \$schema): void
    {
        \$schema->create('{$tableName}', function (Blueprint \$table): void {
            \$table->id();
{$columnDefs}            \$table->timestamps();
        });
    }

    public function down(IlluminateSchemaBuilder \$schema): void
    {
        \$schema->drop('{$tableName}');
    }
};
PHP;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
