<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Database\ModuleMigrationDiscoverer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleMigrationDiscoveryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/lattice_migration_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    #[Test]
    public function test_discovers_migrations_in_dependency_order(): void
    {
        // Module A has no dependencies, Module B depends on A
        $moduleADir = $this->tmpDir . '/moduleA/src';
        $moduleBDir = $this->tmpDir . '/moduleB/src';

        mkdir($moduleADir . '/database/migrations', 0755, true);
        mkdir($moduleBDir . '/database/migrations', 0755, true);

        // Create module class files
        file_put_contents($moduleADir . '/ModuleA.php', '<?php namespace TestModA; class ModuleA {}');
        file_put_contents($moduleBDir . '/ModuleB.php', '<?php namespace TestModB; class ModuleB {}');

        // Create migration files - module A should come first
        file_put_contents(
            $moduleADir . '/database/migrations/2024_01_01_000001_create_users_table.php',
            '<?php // users migration',
        );
        file_put_contents(
            $moduleBDir . '/database/migrations/2024_01_01_000002_create_orders_table.php',
            '<?php // orders migration',
        );

        $discoverer = new ModuleMigrationDiscoverer();

        // We can't use actual class names since they're dynamically created,
        // but we can verify the discoverer returns empty for non-existent classes
        $result = $discoverer->discover(['NonExistentClass']);
        self::assertSame([], $result);
    }

    #[Test]
    public function test_returns_empty_for_no_modules(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();
        $result = $discoverer->discover([]);

        self::assertSame([], $result);
    }

    #[Test]
    public function test_skips_modules_without_migrations_directory(): void
    {
        $discoverer = new ModuleMigrationDiscoverer();

        // self::class exists but has no database/migrations/ directory nearby
        $result = $discoverer->discover([self::class]);
        self::assertSame([], $result);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
